<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class BackupProduction extends Command
{
    protected $signature = 'motel:backup {directory : Private backup root outside the public directory}';

    protected $description = 'Create a private SQLite, uploads and environment backup with a verification manifest';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('This backup procedure supports the production SQLite database only.');

            return self::FAILURE;
        }
        $root = $this->argument('directory');
        File::ensureDirectoryExists($root, 0700, true);
        $root = realpath($root);
        if (! $root || str_starts_with($root.'/', public_path().'/') || str_starts_with($root.'/', storage_path('app').'/')) {
            $this->error('Choose a private directory outside public and uploads.');

            return self::FAILURE;
        }
        $destination = $root.'/'.now()->utc()->format('Ymd\THis\Z').'-'.bin2hex(random_bytes(3));
        File::makeDirectory($destination, 0700);
        $alreadyDown = app()->isDownForMaintenance();
        try {
            if (! $alreadyDown) {
                $this->callSilent('down', ['--retry' => 30]);
            }
            $pdo = DB::connection()->getPdo();
            $pdo->exec('VACUUM INTO '.$pdo->quote($destination.'/database.sqlite'));
            File::copyDirectory(storage_path('app'), $destination.'/uploads');
            if (File::exists(base_path('.env'))) {
                File::copy(base_path('.env'), $destination.'/environment');
            }
            $database = new \PDO('sqlite:'.$destination.'/database.sqlite');
            $counts = [];
            foreach (['users', 'rooms', 'stays', 'payments', 'expenses', 'audit_logs'] as $table) {
                $counts[$table] = (int) $database->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
            }
            $hashes = [];
            foreach (File::allFiles($destination) as $file) {
                chmod($file->getPathname(), 0600);
                $hashes[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
            }
            File::put($destination.'/manifest.json', json_encode(['version' => 1, 'created_at' => now()->toIso8601String(), 'counts' => $counts, 'hashes' => $hashes], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            chmod($destination.'/manifest.json', 0600);
            $this->info('Backup created: '.$destination);

            return $this->call('motel:verify-backup', ['directory' => $destination]);
        } catch (Throwable $error) {
            $this->error('Backup incomplete: '.$error->getMessage());

            return self::FAILURE;
        } finally {
            if (! $alreadyDown) {
                $this->callSilent('up');
            }
        }
    }
}
