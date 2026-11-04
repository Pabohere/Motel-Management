<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class VerifyBackup extends Command
{
    protected $signature = 'motel:verify-backup {directory : Backup containing manifest.json}';

    protected $description = 'Restore a backup to a temporary directory and verify hashes, SQLite integrity and record counts';

    public function handle(): int
    {
        $source = realpath($this->argument('directory'));
        $temporary = sys_get_temp_dir().'/motel-restore-'.bin2hex(random_bytes(8));
        try {
            if (! $source) {
                throw new \RuntimeException('Backup directory does not exist.');
            }
            $manifest = json_decode(File::get($source.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['version'] ?? null) !== 1 || empty($manifest['hashes']['database.sqlite'])) {
                throw new \RuntimeException('Invalid manifest.');
            }
            File::makeDirectory($temporary, 0700);
            foreach ($manifest['hashes'] as $path => $hash) {
                if (! is_string($path) || str_contains($path, '..') || str_starts_with($path, '/') || is_link($source.'/'.$path) || ! is_file($source.'/'.$path) || ! hash_equals($hash, hash_file('sha256', $source.'/'.$path))) {
                    throw new \RuntimeException('Backup file missing, unsafe or corrupt: '.$path);
                }
                File::ensureDirectoryExists(dirname($temporary.'/'.$path), 0700, true);
                File::copy($source.'/'.$path, $temporary.'/'.$path);
            }
            $database = new \PDO('sqlite:'.$temporary.'/database.sqlite');
            if ($database->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $database->query('PRAGMA foreign_key_check')->fetchAll()) {
                throw new \RuntimeException('SQLite integrity check failed.');
            }
            foreach ($manifest['counts'] as $table => $count) {
                if (! preg_match('/^[a-z_]+$/', $table) || (int) $database->query('SELECT COUNT(*) FROM '.$table)->fetchColumn() !== $count) {
                    throw new \RuntimeException('Record count differs: '.$table);
                }
            }
            foreach (['stay_identity_documents' => 'path', 'payments' => 'receipt_path', 'users' => 'photo_path'] as $table => $column) {
                $where = $table === 'stay_identity_documents' ? ' AND deleted_at IS NULL' : '';
                foreach ($database->query('SELECT '.$column.' FROM '.$table.' WHERE '.$column.' IS NOT NULL'.$where)->fetchAll(\PDO::FETCH_COLUMN) as $path) {
                    if (str_contains($path, '..') || ! is_file($temporary.'/uploads/private/'.$path)) {
                        throw new \RuntimeException('Referenced private upload missing: '.$table);
                    }
                }
            }
            $this->info('Restore verified: file hashes, SQLite integrity, foreign keys, record counts and private upload references.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('Backup verification failed: '.$error->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($temporary);
        }
    }
}
