<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Daily backup of the SQLite database (plus .env, which holds APP_KEY).
 *
 * Runs from Windows Task Scheduler ("DMS Daily Backup", 02:00). Each run
 * writes storage/backups/auto/dms-YYYYMMDD-HHMMSS.zip and deletes zips older
 * than --keep days. VACUUM INTO gives a consistent copy even while the app
 * is being used.
 */
class BackupDatabase extends Command
{
    protected $signature = 'dms:backup {--keep=30 : Days of backups to keep}';

    protected $description = 'Back up the SQLite database and .env to storage/backups/auto';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->error('dms:backup only supports the sqlite connection.');

            return self::FAILURE;
        }

        $dir = storage_path('backups/auto');
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Ymd-His');
        $sqliteCopy = $dir.DIRECTORY_SEPARATOR."dms-{$stamp}.sqlite";
        $zipPath = $dir.DIRECTORY_SEPARATOR."dms-{$stamp}.zip";

        DB::statement('VACUUM INTO ?', [$sqliteCopy]);

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            File::delete($sqliteCopy);
            $this->error("Could not create {$zipPath}");

            return self::FAILURE;
        }

        $zip->addFile($sqliteCopy, 'database.sqlite');

        if (File::exists(base_path('.env'))) {
            $zip->addFile(base_path('.env'), 'env.txt');
        }

        $zip->close();
        File::delete($sqliteCopy);

        $cutoff = now()->subDays((int) $this->option('keep'))->getTimestamp();
        $deleted = 0;

        foreach (File::glob($dir.DIRECTORY_SEPARATOR.'dms-*.zip') as $old) {
            if (File::lastModified($old) < $cutoff) {
                File::delete($old);
                $deleted++;
            }
        }

        $size = round(File::size($zipPath) / 1024 / 1024, 2);
        $this->info("Backup written: {$zipPath} ({$size} MB). Old backups removed: {$deleted}.");

        return self::SUCCESS;
    }
}
