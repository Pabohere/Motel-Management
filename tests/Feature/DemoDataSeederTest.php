<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Floor;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_varied_repeatable_and_preserves_existing_records(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(16, 0));
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
        $this->assertDatabaseCount('stay_guests', 53);
        $this->assertDatabaseCount('reservations', 7);
        $this->assertSame(3, Reservation::where('status', 'booked')->count());
        $this->assertSame(1, Reservation::where('status', 'completed')->count());
        $this->assertSame(1, Reservation::where('status', 'checked_in')->count());
        $this->assertSame(1, Reservation::where('status', 'cancelled')->count());
        $this->assertSame(1, Reservation::where('status', 'no_show')->count());
        $this->assertDatabaseCount('stay_extra_beds', 6);
        $this->assertSame(1, Payment::whereNotNull('reversal_of_id')->count());
        $this->assertSame(1, Payment::whereNotNull('receipt_path')->count());
        $this->assertSame(1, Room::where('status', 'cleaning')->count());
        $this->assertSame(1, Room::where('status', 'cleaned')->count());
        $this->assertSame(1, Room::where('status', 'inspected')->count());
        $this->assertSame(1, Stay::whereNull('check_out_at')->where('expected_check_out_at', '<', now())->count());
        $this->assertSame(5, Stay::where('guest_name', 'like', 'Demo %')->withCount('identityDocuments')->get()->sum('identity_documents_count'));
        Storage::disk('local')->assertExists('guest-identities/demo-identity-41.pdf');
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get('guest-identities/demo-identity-41.pdf'));

        $sampleRoom = Room::where('number', '606')->firstOrFail();
        $sampleRoom->update(['status' => 'available']);
        $this->artisan('db:seed', ['--class' => 'DemoDataSeeder', '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(51, Stay::count());
        $this->assertSame(29, Room::count());
        $this->assertSame(12, Expense::count());
        $this->assertDatabaseCount('reservations', 7);
        $this->assertDatabaseCount('stay_guests', 53);
        $this->assertDatabaseCount('stay_extra_beds', 6);
        $this->assertSame('available', $sampleRoom->fresh()->status);
        $this->assertSame('Existing Guest', $existingStay->fresh()->guest_name);
        $this->assertSame('occupied', $existingRoom->fresh()->status);
    }

    public function test_sample_booking_can_check_in_pay_check_out_and_complete_housekeeping(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(16, 0));
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);
        $this->seed(DemoDataSeeder::class);
        $booking = Reservation::where('notes', 'DEMO-WORKFLOW:family')->firstOrFail();

        $this->post(route('reservations.check-in', $booking))->assertSessionHasNoErrors();
        $stay = $booking->stay()->firstOrFail();
        $this->assertSame(3, $stay->guest_count);
        $this->assertSame(170000, $stay->balance);
        $this->assertSame(1, $stay->extraBeds()->whereNull('returned_at')->count());
        $this->post(route('stays.payments', $stay), ['amount' => 170000, 'method' => 'transfer', 'reference' => 'DEMO-TRAINING'])->assertSessionHasNoErrors();
        $this->post(route('stays.checkout', $stay))->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame(0, $stay->fresh()->balance);
        $this->assertSame(0, $stay->extraBeds()->whereNull('returned_at')->count());
        $room = $stay->room;
        $this->assertSame('dirty', $room->fresh()->status);
        foreach (['cleaning', 'cleaned', 'inspected', 'available'] as $status) {
            $this->patch(route('rooms.status', $room), ['status' => $status])->assertSessionHasNoErrors();
        }
        $this->assertSame('available', $room->fresh()->status);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('arrivals', 1)->assertViewHas('overdue', 1);
        $this->get(route('reports.monthly'))->assertOk();
    }

    public function test_conflicting_real_room_aborts_without_adding_or_changing_records(): void
    {
        User::factory()->create(['role' => 'manager']);
        $room = Room::create(['number' => '501', 'floor_id' => Floor::create(['number' => 1])->id,
            'room_type_id' => RoomType::create(['name' => 'Real room'])->id, 'rate' => 99000, 'status' => 'maintenance']);

        try {
            $this->seed(DemoDataSeeder::class);
            $this->fail('Seeding must reject a real room occupying a demo number.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('501', $error->getMessage());
        }

        $this->assertDatabaseCount('rooms', 1);
        $this->assertDatabaseCount('floors', 1);
        $this->assertDatabaseCount('room_types', 1);
        $this->assertDatabaseCount('stays', 0);
        $this->assertSame('maintenance', $room->fresh()->status);
        $this->assertSame(99000, $room->fresh()->rate);
    }

    public function test_live_demo_command_requires_explicit_production_authorization(): void
    {
        $this->app->instance('env', 'production');

        $this->artisan('motel:seed-workflow-demo')->expectsOutputToContain('--allow-production')->assertExitCode(1);

        $this->assertDatabaseCount('rooms', 0);
        $this->assertDatabaseCount('stays', 0);
    }

    public function test_live_demo_command_does_not_seed_when_backup_verification_fails(): void
    {
        $this->app->instance('env', 'production');
        Artisan::all();
        Artisan::registerCommand(Artisan::command('motel:backup {directory}', fn () => 1));

        $this->artisan('motel:seed-workflow-demo', ['--allow-production' => true])
            ->expectsOutputToContain('backup did not pass verification')->assertExitCode(1);

        $this->assertDatabaseCount('rooms', 0);
        $this->assertDatabaseCount('stays', 0);
    }

    public function test_live_demo_command_seeds_only_after_successful_backup(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(16, 0));
        Storage::fake('local');
        User::factory()->create(['role' => 'manager']);
        $this->app->instance('env', 'production');
        Artisan::all();
        $backupRan = false;
        Artisan::registerCommand(Artisan::command('motel:backup {directory}', function () use (&$backupRan) {
            $backupRan = Room::count() === 0 && Stay::count() === 0;

            return 0;
        }));

        $exitCode = Artisan::call('motel:seed-workflow-demo', ['--allow-production' => true]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertTrue($backupRan);
        $this->assertDatabaseCount('rooms', 28);
        $this->assertDatabaseCount('stays', 50);
        $this->assertDatabaseCount('reservations', 7);
    }
}
