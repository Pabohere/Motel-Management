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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroupOccupancyTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(10, 0));
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $this->room = Room::create([
            'number' => '201', 'floor_id' => Floor::create(['number' => 2])->id,
            'room_type_id' => RoomType::create(['name' => 'Family'])->id,
            'rate' => 35000, 'capacity' => 2, 'max_extra_beds' => 2, 'extra_bed_rate' => 5000,
        ]);
    }

    private function companion(string $name = 'Child guest', string $type = 'child'): array
    {
        return ['name' => $name, 'type' => $type, 'gender' => 'female', 'age' => $type === 'child' ? 5 : 30];
    }

    private function data(array $overrides = []): array
    {
        return [...[
            'request_key' => (string) Str::uuid(), 'room_id' => $this->room->id,
            'guest_name' => 'Primary guest', 'gender' => 'male', 'phone' => '09123456',
            'check_in_at' => now()->subMinute()->toDateTimeString(),
            'expected_check_out_at' => now()->addDays(2)->toDateTimeString(),
            'room_charge' => 70000, 'deposit' => 0, 'method' => 'cash', 'payment_term' => 'credit',
            'companions' => [$this->companion('Partner', 'adult'), $this->companion()],
            'extra_beds' => 1, 'extra_bed_nights' => 2, 'extra_bed_rate' => 5000,
        ], ...$overrides];
    }

    private function editData(Stay $stay, array $overrides = []): array
    {
        return [...[
            'guest_name' => $stay->guest_name, 'gender' => $stay->gender, 'phone' => $stay->phone,
            'expected_check_out_at' => $stay->expected_check_out_at->toDateTimeString(),
        ], ...$overrides];
    }

    public function test_family_check_in_tracks_people_and_charges_beds_once_on_retry(): void
    {
        $data = $this->data(['deposit' => 80000, 'payment_term' => 'immediate']);
        $this->post(route('stays.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('stays.store'), $data)->assertSessionHasNoErrors();
        $stay = Stay::firstOrFail();
        $this->assertDatabaseCount('stays', 1);
        $this->assertDatabaseCount('stay_guests', 3);
        $this->assertDatabaseCount('stay_extra_beds', 1);
        $this->assertDatabaseCount('charges', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(80000, $stay->total);
        $this->assertSame(0, $stay->balance);
        $this->assertSame(1, $stay->guests()->where('type', 'child')->count());
        $this->get(route('stays.show', $stay))->assertOk()->assertSee('Partner')->assertSee('Child guest');
        $this->post(route('stays.store'), [...$data, 'companions' => [$this->companion('Different')]])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('charges', 1);
    }

    public function test_capacity_and_extra_bed_limits_include_children_and_are_atomic(): void
    {
        $this->post(route('stays.store'), $this->data(['extra_beds' => 0]))->assertSessionHasErrors('companions');
        $this->post(route('stays.store'), $this->data(['extra_beds' => 3]))->assertSessionHasErrors('extra_beds');
        $this->post(route('stays.store'), $this->data(['extra_bed_rate' => 100000000]))->assertSessionHasErrors('extra_bed_rate');
        $this->assertDatabaseCount('stays', 0);
        $this->assertDatabaseCount('stay_guests', 0);
        $this->assertDatabaseCount('charges', 0);
        $this->assertSame('available', $this->room->fresh()->status);
    }

    public function test_full_payment_includes_beds_and_credit_cannot_overpay(): void
    {
        $this->post(route('stays.store'), $this->data(['payment_term' => 'immediate', 'deposit' => 70000]))->assertSessionHasErrors('deposit');
        $this->post(route('stays.store'), $this->data(['deposit' => 80001]))->assertSessionHasErrors('deposit');
        $this->post(route('stays.store'), $this->data(['deposit' => 75000]))->assertSessionHasNoErrors();
        $this->assertSame(5000, Stay::firstOrFail()->balance);
    }

    public function test_reservation_party_and_bed_quote_survive_edits_and_conversion(): void
    {
        $data = $this->data();
        $data['arrival_at'] = now()->addHour()->toDateTimeString();
        $data['departure_at'] = now()->addDays(2)->toDateTimeString();
        $this->post(route('reservations.store'), $data)->assertSessionHasNoErrors();
        $booking = Reservation::firstOrFail();
        $this->put(route('reservations.update', $booking), [
            'arrival_at' => $data['arrival_at'], 'departure_at' => $data['departure_at'], 'room_charge' => 70000,
        ])->assertSessionHasNoErrors();
        $this->assertCount(2, $booking->fresh()->companions);
        $this->assertSame(1, $booking->fresh()->extra_beds);
        $this->get(route('reservations.edit', $booking))->assertOk()->assertSee('Partner');
        $this->post(route('reservations.check-in', $booking))->assertSessionHasNoErrors();
        $stay = $booking->stay()->firstOrFail();
        $this->assertSame(3, $stay->guest_count);
        $this->assertSame(80000, $stay->total);
        $this->assertSame(5000, $stay->extraBeds()->firstOrFail()->rate);
    }

    public function test_beds_can_be_added_for_free_or_paid_and_returned_without_erasing_charges(): void
    {
        $stay = app(FrontDesk::class)->checkIn($this->data(['companions' => [], 'extra_beds' => 0]));
        $data = ['quantity' => 1, 'nights' => 2, 'rate' => 5000, 'request_key' => (string) Str::uuid()];
        $url = route('stays.extra-beds.store', $stay);
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, [...$data, 'rate' => 6000])->assertSessionHasErrors('request_key');
        $this->assertDatabaseCount('stay_extra_beds', 1);
        $bed = $stay->extraBeds()->firstOrFail();
        $this->patch(route('stays.extra-beds.return', [$stay, $bed]))->assertSessionHasNoErrors();
        $this->patch(route('stays.extra-beds.return', [$stay, $bed]))->assertSessionHasNoErrors();
        $this->assertNotNull($bed->fresh()->returned_at);
        $this->assertSame(80000, $stay->fresh()->total);
        $this->post($url, [...$data, 'rate' => 0, 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('charges', 1);
        $this->post(route('stays.checkout', $stay))->assertSessionHasNoErrors();
        $this->assertSame(0, $stay->extraBeds()->whereNull('returned_at')->count());
        $this->post($url, [...$data, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('operation');
    }

    public function test_cannot_remove_needed_beds_or_exceed_capacity_while_editing_guests(): void
    {
        $stay = app(FrontDesk::class)->checkIn($this->data());
        $bed = $stay->extraBeds()->firstOrFail();
        $this->patch(route('stays.extra-beds.return', [$stay, $bed]))->assertSessionHasErrors('companions');
        $this->assertNull($bed->fresh()->returned_at);
        $this->put(route('stays.update', $stay), $this->editData($stay, ['companions' => array_fill(0, 3, $this->companion())]))->assertSessionHasErrors('companions');
        $this->assertSame(3, $stay->guest_count);
        $this->put(route('stays.update', $stay), $this->editData($stay, ['companions_present' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(1, $stay->fresh()->guest_count);
        $this->patch(route('stays.extra-beds.return', [$stay, $bed]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stay_guests', 3);
    }

    public function test_guest_ids_and_identity_documents_are_scoped_and_limits_apply_per_person(): void
    {
        Storage::fake('local');
        $stay = app(FrontDesk::class)->checkIn($this->data());
        $guest = $stay->guests()->where('is_primary', false)->firstOrFail();
        $primary = $stay->guests()->where('is_primary', true)->firstOrFail();
        for ($i = 0; $i < 5; $i++) {
            $stay->identityDocuments()->create(['stay_guest_id' => $primary->id, 'path' => 'guest-identities/primary-'.$i.'.pdf']);
        }
        $this->put(route('stays.update', $stay), $this->editData($stay, [
            'identity_guest_id' => $guest->id, 'identity_documents' => [UploadedFile::fake()->create('child.pdf', 10, 'application/pdf')],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, $guest->identityDocuments()->count());
        $this->assertSame(6, $stay->identityDocuments()->count());
        $this->put(route('stays.update', $stay), $this->editData($stay, [
            'identity_guest_id' => $primary->id, 'identity_documents' => [UploadedFile::fake()->create('sixth.pdf', 10, 'application/pdf')],
        ]))->assertSessionHasErrors('identity_documents');
        $foreignRoom = $this->room->replicate();
        $foreignRoom->number = '202';
        $foreignRoom->status = 'available';
        $foreignRoom->save();
        $other = app(FrontDesk::class)->checkIn($this->data(['room_id' => $foreignRoom->id]));
        $foreignGuest = $other->guests()->firstOrFail();
        $this->put(route('stays.update', $stay), $this->editData($stay, [
            'identity_guest_id' => $foreignGuest->id, 'identity_documents' => [UploadedFile::fake()->create('wrong.pdf', 10, 'application/pdf')],
        ]))->assertSessionHasErrors('identity_guest_id');
        $this->put(route('stays.update', $stay), $this->editData($stay, [
            'companions' => [[...$this->companion(), 'id' => $foreignGuest->id]],
        ]))->assertSessionHasErrors('companions.0.id');
        $this->assertSame(3, $stay->fresh()->guest_count);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->get(route('stays.identity.show', [$other, $guest->identityDocuments()->firstOrFail()]))->assertNotFound();
    }

    public function test_room_settings_cannot_invalidate_active_or_booked_groups(): void
    {
        $stay = app(FrontDesk::class)->checkIn($this->data());
        $settings = $this->room->only(['number', 'floor_id', 'room_type_id', 'rate']);
        $this->put(route('rooms.update', $this->room), [...$settings, 'capacity' => 1])->assertSessionHasErrors('companions');
        $this->put(route('rooms.update', $this->room), [...$settings, 'max_extra_beds' => 0])->assertSessionHasErrors('extra_beds');
        $this->assertSame(2, $this->room->fresh()->capacity);
        $this->post(route('stays.checkout', $stay))->assertSessionHasNoErrors();
        Reservation::create([
            'room_id' => $this->room->id, 'guest_name' => 'Booked family', 'phone' => '09', 'gender' => 'female',
            'arrival_at' => now()->addDay(), 'departure_at' => now()->addDays(3),
            'room_charge' => 70000, 'created_by' => auth()->id(),
            'companions' => [$this->companion(), $this->companion()], 'extra_beds' => 1,
        ]);
        $this->put(route('rooms.update', $this->room), [...$settings, 'max_extra_beds' => 0])->assertSessionHasErrors('extra_beds');
    }

    public function test_couple_room_and_reports_count_people_separately_from_rooms(): void
    {
        $stay = app(FrontDesk::class)->checkIn($this->data(['companions' => [$this->companion('Partner', 'adult')], 'extra_beds' => 0]));
        $this->assertSame(2, $stay->guest_count);
        $this->assertDatabaseCount('charges', 0);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('checkIns', 2);
        $this->get(route('reports.daily'))->assertOk()->assertViewHas('occupied', 1)
            ->assertViewHas('checkIns', fn ($stays) => $stays->sum('guest_count') === 2);
        $this->get(route('stays.index', ['q' => 'Partner']))->assertOk()->assertViewHas('stays', fn ($stays) => $stays->count() === 1);
        foreach (['stays.create', 'stays.edit', 'rooms.edit', 'rooms.index', 'reservations.create'] as $name) {
            $this->get(route($name, match ($name) {
                'stays.edit' => [$stay], 'rooms.edit' => [$this->room], default => [],
            }))->assertOk();
        }
    }
}
