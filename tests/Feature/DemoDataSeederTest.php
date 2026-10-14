<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_varied_repeatable_and_preserves_existing_records(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager']);
        $existingRoom = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id,
            'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 35000]);
        $existingStay = Stay::create([
            'room_id' => $existingRoom->id,
            'active_room_id' => $existingRoom->id,
            'guest_name' => 'Existing Guest',
            'gender' => 'female',
            'phone' => '09123456789',
            'check_in_at' => now()->subHour(),
            'expected_check_out_at' => now()->addDay(),
            'room_charge' => 35000,
            'created_by' => $manager->id,
        ]);
        $existingRoom->update(['status' => 'occupied']);

        $this->artisan('db:seed', ['--class' => 'DemoDataSeeder', '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(51, Stay::count());
        $this->assertSame(29, Room::count());
        $this->assertSame(12, Expense::count());
        $this->assertSame(10, Stay::where('guest_name', 'like', 'Demo %')->whereNull('check_out_at')->count());
        $this->assertSame(40, Stay::where('guest_name', 'like', 'Demo %')->whereNotNull('check_out_at')->count());
        $this->assertSame(25, Stay::where('guest_name', 'like', 'Demo %')->where('gender', 'male')->count());
        $this->assertSame(25, Stay::where('guest_name', 'like', 'Demo %')->where('gender', 'female')->count());
        $this->assertSame(4, Room::whereHas('roomType', fn ($q) => $q->where('name', 'like', 'Demo %'))->where('status', 'dirty')->count());
        $this->assertSame(3, Room::whereHas('roomType', fn ($q) => $q->where('name', 'like', 'Demo %'))->where('status', 'maintenance')->count());
        $this->assertSame(10, Room::whereHas('roomType', fn ($q) => $q->where('name', 'like', 'Demo %'))->where('status', 'occupied')->count());
        $this->assertTrue(Stay::where('guest_name', 'like', 'Demo %')->whereNotNull('check_out_at')->with(['charges', 'payments'])->get()->every(fn (Stay $stay) => $stay->balance === 0));
        $this->assertSame(5, Stay::where('guest_name', 'like', 'Demo %')->withCount('identityDocuments')->get()->sum('identity_documents_count'));
        Storage::disk('local')->assertExists('guest-identities/demo-identity-41.pdf');
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get('guest-identities/demo-identity-41.pdf'));

        $this->artisan('db:seed', ['--class' => 'DemoDataSeeder', '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(51, Stay::count());
        $this->assertSame(29, Room::count());
        $this->assertSame(12, Expense::count());
        $this->assertSame('Existing Guest', $existingStay->fresh()->guest_name);
        $this->assertSame('occupied', $existingRoom->fresh()->status);
    }
}
