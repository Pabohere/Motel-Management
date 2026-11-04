<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ProductionCheck extends Command
{
    protected $signature = 'motel:production-check';

    protected $description = 'Check release configuration without exposing credentials or changing business records';

    public function handle(): int
    {
        $checks = [
            'Production environment' => app()->environment('production'),
            'Debug disabled' => ! config('app.debug'),
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
            'Encryption key configured' => filled(config('app.key')),
            'Secure session cookie' => config('session.secure') === true,
            'HTTP-only session cookie' => config('session.http_only') === true,
            'Same-site session cookie' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Private storage route disabled' => ! config('filesystems.disks.local.serve'),
            'SQLite production database' => DB::connection()->getDriverName() === 'sqlite',
            'Backup directory configured' => filled(config('app.backup_directory')) && is_dir(config('app.backup_directory')) && is_writable(config('app.backup_directory')),
        ];
        $publicStorage = realpath(public_path('storage'));
        $privateStorage = realpath(Storage::disk('local')->path(''));
        $checks['Private upload directory writable'] = $privateStorage && is_writable($privateStorage);
        $checks['Private uploads outside public storage'] = ! $publicStorage || ! $privateStorage || ! str_starts_with($privateStorage.'/', $publicStorage.'/');
        $checks['No pending migrations'] = Schema::hasTable('migrations') && ! array_diff(array_keys(app('migrator')->getMigrationFiles(database_path('migrations'))), app('migration.repository')->getRan());
        if (DB::connection()->getDriverName() === 'sqlite') {
            $checks['SQLite integrity'] = DB::selectOne('PRAGMA integrity_check')->integrity_check === 'ok';
            $checks['SQLite foreign keys'] = DB::select('PRAGMA foreign_key_check') === [];
        }
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS ' : 'FAIL ').$label);
        }
        $this->line('Also verify cPanel cron execution, off-host backups, HTTPS responses and physical Android/iOS installation.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
