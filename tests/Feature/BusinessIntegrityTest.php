<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\User;
use App\Services\FrontDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(10, 0));
        $this->staff = User::factory()->create();
        $this->actingAs($this->staff);
        $this->room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 35000]);
    }

    private function data(): array
    {
        return ['room_id' => $this->room->id, 'guest_name' => 'Integrity guest', 'gender' => 'female', 'phone' => '09', 'check_in_at' => now()->subMinute()->toDateTimeString(), 'expected_check_out_at' => now()->addDay()->toDateTimeString(), 'room_charge' => 35000, 'deposit' => 10000, 'method' => 'cash'];
    }

    public function test_omitted_null_and_immediate_terms_cannot_bypass_full_payment(): void
    {
        foreach ([[], ['payment_term' => null], ['payment_term' => 'immediate']] as $term) {
            $this->post(route('stays.store'), [...$this->data(), ...$term])->assertSessionHasErrors('deposit');
        }
        $this->assertDatabaseCount('stays', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->post(route('stays.store'), [...$this->data(), 'deposit' => 35000])->assertSessionHasNoErrors();
        $this->assertSame('immediate', Stay::firstOrFail()->payment_term);
    }

    public function test_credit_permits_partial_payment_but_service_also_enforces_default_immediate(): void
    {
        try {
            app(FrontDesk::class)->checkIn($this->data());
            $this->fail('Service accepted unpaid immediate stay.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('deposit', $error->errors());
        }
        $this->post(route('stays.store'), [...$this->data(), 'payment_term' => 'credit'])->assertSessionHasNoErrors();
        $this->assertSame(25000, Stay::firstOrFail()->balance);
    }

    public function test_extension_uses_half_open_booking_intervals_and_preserves_state_on_conflict(): void
    {
        $stay = app(FrontDesk::class)->checkIn([...$this->data(), 'payment_term' => 'credit']);
        $booking = Reservation::create(['room_id' => $this->room->id, 'guest_name' => 'Next guest', 'gender' => 'female', 'phone' => '09', 'arrival_at' => $stay->expected_check_out_at, 'departure_at' => now()->addDays(3), 'room_charge' => 35000, 'created_by' => $this->staff->id]);
        $edit = ['guest_name' => $stay->guest_name, 'gender' => 'female', 'phone' => '09', 'expected_check_out_at' => $booking->arrival_at->toDateTimeString()];
        $this->put(route('stays.update', $stay), $edit)->assertSessionHasNoErrors();
        $this->put(route('stays.update', $stay), [...$edit, 'expected_check_out_at' => $booking->arrival_at->copy()->addMinute()->toDateTimeString()])->assertSessionHasErrors('expected_check_out_at');
        $this->assertTrue($stay->fresh()->expected_check_out_at->eq($booking->arrival_at));
        $this->assertSame('booked', $booking->fresh()->status);
    }

    public function test_reservation_lifecycle_completes_once_and_expired_bookings_can_be_marked_no_show(): void
    {
        $booking = Reservation::create(['room_id' => $this->room->id, 'guest_name' => 'Booked guest', 'gender' => 'female', 'phone' => '09', 'arrival_at' => now()->addHour(), 'departure_at' => now()->addDay(), 'room_charge' => 35000, 'created_by' => $this->staff->id]);
        $this->post(route('reservations.check-in', $booking))->assertSessionHasNoErrors();
        $stay = $booking->stay()->firstOrFail();
        $this->post(route('reservations.check-in', $booking))->assertSessionHasErrors('operation');
        $this->post(route('stays.checkout', $stay))->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->post(route('stays.checkout', $stay))->assertSessionHasErrors('operation');
        $this->post(route('reservations.cancel', $booking))->assertSessionHasErrors('operation');
        $expired = Reservation::create(['room_id' => $this->room->id, 'guest_name' => 'Absent guest', 'gender' => 'female', 'phone' => '09', 'arrival_at' => now()->subDays(2), 'departure_at' => now()->subDay(), 'room_charge' => 35000, 'created_by' => $this->staff->id]);
        $this->post(route('reservations.check-in', $expired))->assertSessionHasErrors('operation');
        $this->post(route('reservations.no-show', $expired))->assertSessionHasNoErrors();
        $this->assertSame('no_show', $expired->fresh()->status);
    }

    public function test_checkin_retry_creates_one_stay_and_one_initial_payment(): void
    {
        $data = [...$this->data(), 'deposit' => 35000, 'request_key' => (string) Str::uuid()];
        foreach ([1, 2] as $attempt) {
            $this->post(route('stays.store'), $data)->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('stays', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->post(route('stays.store'), [...$data, 'guest_name' => 'Changed request'])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('stays', 1);
    }

    public function test_reservation_expiring_exactly_now_cannot_check_in(): void
    {
        $booking = Reservation::create(['room_id' => $this->room->id, 'guest_name' => 'Boundary guest', 'gender' => 'female', 'phone' => '09', 'arrival_at' => now()->subDay(), 'departure_at' => now(), 'room_charge' => 35000, 'created_by' => $this->staff->id]);
        $this->post(route('reservations.check-in', $booking))->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('stays', 0);
        $this->post(route('reservations.no-show', $booking))->assertSessionHasNoErrors();
        $this->assertSame('no_show', $booking->fresh()->status);
    }
}
