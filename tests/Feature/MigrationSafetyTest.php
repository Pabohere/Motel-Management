<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationSafetyTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    public function test_upgrade_preserves_legacy_records_and_backfills_completed_reservations(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('payments', 'reversal_of_id'));
        $userId = DB::table('users')->insertGetId(['name' => 'Legacy staff', 'email' => 'legacy@example.test', 'password' => bcrypt('Legacy-Test-Password'), 'role' => 'staff', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 10000, 'status' => 'dirty']);
        $reservation = Reservation::create(['room_id' => $room->id, 'guest_name' => 'Legacy guest', 'gender' => 'female', 'phone' => '09', 'arrival_at' => now()->subDays(2), 'departure_at' => now()->subDay(), 'room_charge' => 10000, 'status' => 'checked_in', 'created_by' => $userId]);
        $stay = Stay::create(['room_id' => $room->id, 'reservation_id' => $reservation->id, 'guest_name' => 'Legacy guest', 'phone' => '09', 'check_in_at' => now()->subDays(2), 'expected_check_out_at' => now()->subDay(), 'check_out_at' => now()->subDay(), 'room_charge' => 10000, 'created_by' => $userId]);
        $payment = Payment::create(['stay_id' => $stay->id, 'amount' => 10000, 'method' => 'cash', 'created_by' => $userId]);
        $document = $stay->identityDocuments()->create(['path' => 'guest-identities/legacy.pdf']);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, Stay::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(10000, $payment->fresh()->amount);
        $this->assertSame(0, $stay->fresh()->balance);
        $this->assertSame('completed', $reservation->fresh()->status);
        $this->assertTrue(User::findOrFail($userId)->canViewDocuments());
        $this->assertSame('guest-identities/legacy.pdf', $document->fresh()->path);
        $this->assertSame('dirty', $room->fresh()->status);
    }
}
