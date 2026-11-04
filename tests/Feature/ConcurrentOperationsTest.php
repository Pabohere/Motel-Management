<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ConcurrentOperationsTest extends TestCase
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

    public function test_two_real_sqlite_processes_cannot_double_book_or_double_check_in(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 10000]);
        $root = sys_get_temp_dir().'/motel-race-'.bin2hex(random_bytes(8));
        File::makeDirectory($root, 0700);
        $worker = <<<'WORKER'
<?php
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.sqlite.database' => $argv[2], 'database.connections.sqlite.transaction_mode' => 'IMMEDIATE', 'database.connections.sqlite.busy_timeout' => 5000]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Auth::loginUsingId((int) $argv[4]);
while (microtime(true) < (float) $argv[6]) { usleep(1000); }
try {
    $data = ['room_id' => (int) $argv[5], 'guest_name' => 'Concurrent guest', 'gender' => 'female', 'phone' => '09', 'room_charge' => 10000, 'payment_term' => 'credit', 'deposit' => 0, 'method' => 'cash', 'check_in_at' => now()->toDateTimeString(), 'expected_check_out_at' => now()->addDay()->toDateTimeString(), 'arrival_at' => now()->addDay()->toDateTimeString(), 'departure_at' => now()->addDays(2)->toDateTimeString()];
    if ($argv[3] === 'checkin') {
        app(App\Services\FrontDesk::class)->checkIn($data);
    } else {
        $request = Illuminate\Http\Request::create('/reservations', 'POST', $data);
        app(App\Http\Controllers\ReservationController::class)->store($request);
    }
    echo 'accepted';
} catch (Illuminate\Validation\ValidationException $error) { echo 'rejected'; }
WORKER;
        try {
            File::put($root.'/worker.php', $worker);
            foreach (['booking' => 'reservations', 'checkin' => 'stays'] as $mode => $table) {
                $database = $root.'/'.$mode.'.sqlite';
                $pdo = DB::connection()->getPdo();
                $pdo->exec('VACUUM INTO '.$pdo->quote($database));
                $processes = [];
                $start = microtime(true) + 0.4;
                foreach ([1, 2] as $attempt) {
                    $pipes = [];
                    $process = proc_open([PHP_BINARY, $root.'/worker.php', base_path(), $database, $mode, $user->id, $room->id, $start], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                    $this->assertIsResource($process);
                    $processes[] = [$process, $pipes];
                }
                $results = [];
                foreach ($processes as [$process, $pipes]) {
                    $results[] = stream_get_contents($pipes[1]);
                    $errors = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $this->assertSame(0, proc_close($process), $errors);
                }
                sort($results);
                $this->assertSame(['accepted', 'rejected'], $results, $mode);
                $restored = new \PDO('sqlite:'.$database);
                $this->assertSame(1, (int) $restored->query('SELECT COUNT(*) FROM '.$table)->fetchColumn());
            }
        } finally {
            File::deleteDirectory($root);
        }
    }
}
