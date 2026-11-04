<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleaning_inspection_and_ready_are_required_and_tracked(): void
    {
        $staff = User::factory()->create();
        $manager = User::factory()->create(['role' => 'manager']);
        $room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 10000, 'status' => 'dirty']);
        $this->actingAs($staff);
        $url = route('rooms.status', $room);
        $this->patch($url, ['status' => 'available'])->assertSessionHasErrors('operation');
        $this->patch($url, ['status' => 'cleaned'])->assertSessionHasErrors('operation');
        $this->patch($url, ['status' => 'cleaning'])->assertSessionHasNoErrors();
        $this->assertSame($staff->id, $room->fresh()->housekeeping_by);
        $this->assertNotNull($room->fresh()->cleaning_started_at);
        $this->patch($url, ['status' => 'cleaned'])->assertSessionHasNoErrors();
        $this->assertNotNull($room->fresh()->cleaned_at);
        $this->patch($url, ['status' => 'inspected'])->assertSessionHasErrors('operation');
        $this->get(route('stays.create', ['room' => $room->id]))->assertOk()->assertDontSee('value="'.$room->id.'" data-rate', false);
        $this->actingAs($manager)->patch($url, ['status' => 'inspected'])->assertSessionHasNoErrors();
        $this->assertNotNull($room->fresh()->inspected_at);
        $this->patch($url, ['status' => 'available'])->assertSessionHasNoErrors();
        $this->assertSame('available', $room->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'room.inspected', 'user_id' => $manager->id]);
        $this->get(route('rooms.show', $room))->assertOk()->assertSee($staff->name);
    }
}
