<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates and restores DMS backups.
 *
 * A backup is a zip holding `database.sqlite` (a consistent VACUUM INTO copy)
 * and `env.txt`. Restore copies the data of every table back into the live
 * database inside one transaction, so the running app never sees a half
 * restored database and the database file itself is never swapped out
 * (which Windows would refuse while Apache has it open).
 */
class BackupService
{
    /** Tables whose contents are never restored (runtime state, not business data). */
    private const SKIP_TABLES = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens',
    ];

    public function directory(): string
    {
        $dir = storage_path('backups'.DIRECTORY_SEPARATOR.'auto');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /**
     * @return string full path of the new zip
     */
    public function create(string $label = ''): string
    {
        $this->ensureSqlite();

        $stamp = now()->format('Ymd-His');
        $name = 'dms-'.$stamp.($label !== '' ? '-'.$label : '');
        $sqliteCopy = $this->directory().DIRECTORY_SEPARATOR.$name.'.sqlite';
        $zipPath = $this->directory().DIRECTORY_SEPARATOR.$name.'.zip';

        DB::statement('VACUUM INTO ?', [$sqliteCopy]);

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            File::delete($sqliteCopy);
            throw new \RuntimeException("Could not create {$zipPath}");
        }

        $zip->addFile($sqliteCopy, 'database.sqlite');

        if (File::exists(base_path('.env'))) {
            $zip->addFile(base_path('.env'), 'env.txt');
        }

        $zip->close();
        File::delete($sqliteCopy);

        return $zipPath;
    }

    /**
     * Delete backups older than $days (safety backups taken before a restore
     * are kept like any other).
     */
    public function prune(int $days): int
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $deleted = 0;

        foreach (File::glob($this->directory().DIRECTORY_SEPARATOR.'dms-*.zip') as $file) {
            if (File::lastModified($file) < $cutoff) {
                File::delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @return array<int, array{name: string, size: int, time: int}>
     */
    public function list(int $limit = 20): array
    {
        return collect(File::glob($this->directory().DIRECTORY_SEPARATOR.'dms-*.zip'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => File::size($f), 'time' => File::lastModified($f)])
            ->sortByDesc('time')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Full path of a stored backup, or null when the name is not a valid one.
     */
    public function path(string $name): ?string
    {
        if (! preg_match('/^dms-[0-9]{8}-[0-9]{6}(-[a-z0-9-]+)?\.zip$/', $name)) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return File::exists($path) ? $path : null;
    }

    /**
     * Restore from a backup zip (or a bare .sqlite file).
     *
     * @return array{tables: int, rows: int, safety_backup: string}
     */
    public function restore(string $file): array
    {
        $this->ensureSqlite();

        $source = $this->extractDatabase($file);

        try {
            $this->validateSource($source);

            $safety = $this->create('pre-restore');

            $result = $this->copyData($source);
        } finally {
            File::delete($source);
        }

        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('migrate', ['--force' => true]);

        return $result + ['safety_backup' => basename($safety)];
    }

    private function ensureSqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            throw new \RuntimeException('Backup and restore only support the SQLite database.');
        }
    }

    private function extractDatabase(string $file): string
    {
        $target = storage_path('backups'.DIRECTORY_SEPARATOR.'restore-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($target));

        $head = (string) file_get_contents($file, false, null, 0, 16);

        if (str_starts_with($head, 'SQLite format 3')) {
            File::copy($file, $target);

            return $target;
        }

        $zip = new \ZipArchive();

        if ($zip->open($file) !== true) {
            throw new \RuntimeException('This is not a DMS backup file (expected a .zip or .sqlite).');
        }

        $data = $zip->getFromName('database.sqlite');
        $zip->close();

        if ($data === false) {
            throw new \RuntimeException('The zip does not contain database.sqlite.');
        }

        File::put($target, $data);

        return $target;
    }

    private function validateSource(string $source): void
    {
        try {
            $pdo = new \PDO('sqlite:'.$source, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            throw new \RuntimeException('The backup database cannot be read: '.$e->getMessage());
        }

        if ($integrity !== 'ok') {
            throw new \RuntimeException('The backup database is damaged (integrity check failed).');
        }

        foreach (['migrations', 'users', 'products'] as $required) {
            if (! in_array($required, $tables, true)) {
                throw new \RuntimeException("This does not look like a DMS backup (no {$required} table).");
            }
        }

        $backupMigrations = $pdo->query('SELECT migration FROM migrations')->fetchAll(\PDO::FETCH_COLUMN);
        $currentMigrations = DB::table('migrations')->pluck('migration')->all();
        $newer = array_diff($backupMigrations, $currentMigrations);

        if ($newer !== []) {
            throw new \RuntimeException('This backup was made by a newer version of DMS and cannot be restored here.');
        }

        $pdo = null;
    }

    /**
     * @return array{tables: int, rows: int}
     */
    private function copyData(string $source): array
    {
        $conn = DB::connection();
        $tables = 0;
        $rows = 0;

        $conn->statement('PRAGMA foreign_keys = OFF');
        $conn->statement('ATTACH DATABASE ? AS restore_src', [$source]);

        try {
            $srcTables = collect($conn->select("SELECT name FROM restore_src.sqlite_master WHERE type = 'table'"))->pluck('name')->all();
            $liveTables = collect($conn->select("SELECT name FROM main.sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))->pluck('name')->all();

            $conn->transaction(function () use ($conn, $srcTables, $liveTables, &$tables, &$rows) {
                foreach ($liveTables as $table) {
                    if (in_array($table, self::SKIP_TABLES, true)) {
                        continue;
                    }

                    $quoted = '"'.str_replace('"', '""', $table).'"';
                    $conn->statement("DELETE FROM main.{$quoted}");

                    if (! in_array($table, $srcTables, true)) {
                        continue;
                    }

                    $liveCols = collect($conn->select("PRAGMA main.table_info({$quoted})"))->pluck('name');
                    $srcCols = collect($conn->select("PRAGMA restore_src.table_info({$quoted})"))->pluck('name');
                    $cols = $liveCols->intersect($srcCols)
                        ->map(fn ($c) => '"'.str_replace('"', '""', $c).'"')
                        ->implode(', ');

                    $conn->statement("INSERT INTO main.{$quoted} ({$cols}) SELECT {$cols} FROM restore_src.{$quoted}");
                    $rows += (int) $conn->selectOne("SELECT COUNT(*) AS c FROM main.{$quoted}")->c;
                    $tables++;
                }

                $violations = $conn->select('PRAGMA main.foreign_key_check');

                if ($violations !== []) {
                    throw new \RuntimeException('The backup has broken links between records ('.count($violations).' found). Nothing was changed.');
                }
            });
        } finally {
            $conn->statement('DETACH DATABASE restore_src');
            $conn->statement('PRAGMA foreign_keys = ON');
        }

        return ['tables' => $tables, 'rows' => $rows];
    }
}
