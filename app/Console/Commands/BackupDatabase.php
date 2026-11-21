<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Daily backup of the SQLite database (plus .env, which holds APP_KEY).
 *
 * Runs from Windows Task Scheduler ("DMS Daily Backup", 02:00). Each run
 * writes storage/backups/auto/dms-YYYYMMDD-HHMMSS.zip and deletes zips older
 * than --keep days. Restore lives in More > Backup & Restore.
 */
class BackupDatabase extends Command
{
    protected $signature = 'dms:backup {--keep=30 : Days of backups to keep}';

    protected $description = 'Back up the SQLite database and .env to storage/backups/auto';

    public function handle(BackupService $backups): int
    {
        try {
            $zip = $backups->create();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $deleted = $backups->prune((int) $this->option('keep'));
        $size = round(filesize($zip) / 1024 / 1024, 2);

        $this->info("Backup written: {$zip} ({$size} MB). Old backups removed: {$deleted}.");

        return self::SUCCESS;
    }
}
