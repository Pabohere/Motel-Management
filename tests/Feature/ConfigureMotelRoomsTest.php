<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigureMotelRoomsTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_is_previewed_then_applied_idempotently_with_all_rooms_visible(): void
    {
        $this->artisan('motel:configure-rooms')->assertSuccessful();
        $this->assertDatabaseCount('rooms', 0);
        $this->artisan('motel:configure-rooms --apply')->assertSuccessful();
        $this->artisan('motel:configure-rooms --apply')->assertSuccessful();
        $this->assertDatabaseCount('rooms', 40);
        $this->assertDatabaseCount('floors', 4);
        $this->assertDatabaseCount('room_types', 3);
        foreach (Floor::all() as $floor) {
            $this->assertSame(10, $floor->rooms()->count());
            foreach (['Single Room' => [4, 35000], 'Couple Room' => [4, 50000], 'Family Room' => [2, 70000]] as $type => [$count, $rate]) {
                $this->assertSame($count, $floor->rooms()->whereHas('roomType', fn ($query) => $query->where('name', $type))->where('rate', $rate)->count());
            }
        }
        $this->actingAs(User::factory()->create())->get(route('rooms.index'))->assertOk()->assertViewHas('rooms', fn ($rooms) => $rooms->count() === 40)->assertSee('410');
    }

    public function test_extra_rooms_are_archived_and_existing_room_status_and_identity_are_preserved(): void
    {
        $room = $this->room('101', 'dirty');
        $extra = $this->room('UAT-101');
        $this->artisan('motel:configure-rooms --apply')->assertSuccessful();
        $this->assertSame(40, Room::count());
        $this->assertSoftDeleted($extra);
        $this->assertSame('dirty', $room->fresh()->status);
        $this->assertSame(35000, $room->fresh()->rate);
        $this->assertDatabaseHas('audit_logs', ['action' => 'room.archived', 'subject_id' => $extra->id]);
    }

    public function test_active_extra_rooms_or_bookings_block_all_changes(): void
    {
        $extra = $this->room('UAT-101', 'occupied');
        $this->artisan('motel:configure-rooms --apply')->assertFailed();
        $this->assertDatabaseCount('rooms', 1);
        $this->assertFalse($extra->fresh()->trashed());
        $extra->update(['status' => 'available']);
        Reservation::create(['room_id' => $extra->id, 'guest_name' => 'Booked Guest', 'gender' => 'other', 'phone' => '123', 'arrival_at' => now()->addDay(), 'departure_at' => now()->addDays(2), 'room_charge' => 10000, 'status' => 'booked', 'created_by' => User::factory()->create()->id]);
        $this->artisan('motel:configure-rooms --apply')->assertFailed();
        $this->assertDatabaseCount('rooms', 1);
        $this->assertDatabaseCount('room_types', 1);
        $this->assertFalse($extra->fresh()->trashed());
    }

    private function room(string $number, string $status = 'available'): Room
    {
        return Room::create(['number' => $number, 'floor_id' => Floor::firstOrCreate(['number' => 1])->id, 'room_type_id' => RoomType::firstOrCreate(['name' => 'Old Type'])->id, 'rate' => 10000, 'status' => $status]);
    }
}
