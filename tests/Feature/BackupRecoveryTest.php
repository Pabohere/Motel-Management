<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupRecoveryTest extends TestCase
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

    public function test_database_restores_and_corruption_is_detected(): void
    {
        User::factory()->create();
        $root = sys_get_temp_dir().'/motel-backup-test-'.bin2hex(random_bytes(8));
        try {
            $this->artisan('motel:backup', ['directory' => $root])->assertSuccessful();
            $backup = File::directories($root)[0];
            $restored = new \PDO('sqlite:'.$backup.'/database.sqlite');
            $this->assertSame(1, (int) $restored->query('SELECT COUNT(*) FROM users')->fetchColumn());
            $this->assertSame(1, DB::table('users')->count());
            File::append($backup.'/database.sqlite', 'corrupt');
            $this->artisan('motel:verify-backup', ['directory' => $backup])->assertFailed();
            $this->assertFalse(app()->isDownForMaintenance());
        } finally {
            File::deleteDirectory($root);
        }
    }
}
