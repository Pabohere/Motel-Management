<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\FrontDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_find_stay_reference_and_payment_reference_with_safe_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['locale' => 'en']);
        $room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 10000]);
        $stay = app(FrontDesk::class)->checkIn(['room_id' => $room->id, 'guest_name' => 'Reference guest', 'gender' => 'female', 'phone' => '09', 'check_in_at' => now()->toDateTimeString(), 'expected_check_out_at' => now()->addDay()->toDateTimeString(), 'room_charge' => 10000, 'payment_term' => 'credit', 'deposit' => 0]);
        $payment = app(FrontDesk::class)->payment($stay, ['amount' => 5000, 'method' => 'transfer', 'reference' => 'BANK-987']);
        $this->get(route('stays.index', ['q' => '#'.$stay->id]))->assertOk()->assertViewHas('stays', fn ($stays) => $stays->modelKeys() === [$stay->id]);
        $this->get(route('payments.index', ['q' => 'BANK-987']))->assertOk()->assertViewHas('payments', fn ($payments) => $payments->modelKeys() === [$payment->id]);
        $this->get(route('settings'))->assertOk()->assertDontSee(route('users.index'), false)->assertDontSee(route('audit.index'), false);
        $this->get(route('reports.index'))->assertOk()->assertSee(route('reports.daily'), false)->assertDontSee(route('reports.monthly'), false);
    }

    public function test_checkin_sequence_preserves_errors_and_uses_a_submission_key(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['locale' => 'en']);
        Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 10000]);
        $html = $this->get(route('stays.create'))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'name="guest_name"'), strpos($html, 'id="room-select"'));
        $this->assertLessThan(strpos($html, 'name="deposit"'), strpos($html, 'name="guest_name"'));
        $this->assertLessThan(strpos($html, 'data-review-guest'), strpos($html, 'name="deposit"'));
        $this->assertStringContainsString('name="request_key"', $html);
    }

    public function test_validation_preserves_guest_input_and_shows_field_errors(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['locale' => 'en']);
        Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 10000]);
        $this->from(route('stays.create'))->post(route('stays.store'), ['guest_name' => 'Keep this name'])->assertSessionHasErrors('room_id');
        // JSON sessions must be persisted before a second request in this test process.
        session()->reflash();
        session()->save();
        $this->get(route('stays.create'))->assertOk()->assertSee('Keep this name')->assertSee('class="field-error"', false);
    }
}
