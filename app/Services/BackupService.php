<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates and restores DMS backups.
 *
 * A backup is a zip holding `database.sqlite` and `env.txt`, whatever the
 * live database is: on SQLite it is a consistent VACUUM INTO copy, on
 * MySQL/MariaDB the data is exported into a fresh SQLite file built by the
 * normal migrations. So one backup format works for both, and an old SQLite
 * backup can be restored into MySQL. Restore copies the data of every table
 * back into the live database inside one transaction, so the running app
 * never sees a half restored database and no database file is swapped out
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
        $stamp = now()->format('Ymd-His');
        $name = 'dms-'.$stamp.($label !== '' ? '-'.$label : '');
        $sqliteCopy = $this->directory().DIRECTORY_SEPARATOR.$name.'.sqlite';
        $zipPath = $this->directory().DIRECTORY_SEPARATOR.$name.'.zip';

        $this->snapshot($sqliteCopy);

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
     * Write a consistent copy of the live database to $target.
     * VACUUM INTO needs SQLite 3.27+. Older builds (the cPanel server ships
     * 3.26) copy the file while holding a write lock instead, so no other
     * request can change it half-way through the copy.
     */
    private function snapshot(string $target): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->exportToSqlite($target);

            return;
        }

        $pdo = DB::connection()->getPdo();

        if (version_compare($pdo->query('select sqlite_version()')->fetchColumn(), '3.27.0', '>=')) {
            DB::statement('VACUUM INTO ?', [$target]);

            return;
        }

        $pdo->exec('BEGIN IMMEDIATE');

        try {
            File::copy(DB::connection()->getConfig('database'), $target);
        } finally {
            $pdo->exec('ROLLBACK');
        }
    }

    /**
     * MySQL/MariaDB: build an empty SQLite database with the migrations, then
     * copy every business table into it. The reads run inside one
     * REPEATABLE READ transaction, so InnoDB gives a consistent snapshot even
     * while people keep working.
     */
    private function exportToSqlite(string $target): void
    {
        File::put($target, '');
        config(['database.connections.backup_export' => [
            'driver' => 'sqlite', 'database' => $target, 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('backup_export');

        try {
            Artisan::call('migrate', ['--database' => 'backup_export', '--force' => true]);

            $live = DB::connection();
            $export = DB::connection('backup_export');
            $exportTables = $this->tableNames('backup_export');

            if ($live->transactionLevel() === 0) {   // MySQL refuses this inside an open transaction
                $live->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $live->beginTransaction();

            try {
                $export->transaction(function () use ($live, $export, $exportTables) {
                    foreach ($this->tableNames() as $table) {
                        if (in_array($table, self::SKIP_TABLES, true) || ! in_array($table, $exportTables, true)) {
                            continue;
                        }

                        $export->table($table)->delete();
                        $cols = array_values(array_intersect(
                            Schema::getColumnListing($table),
                            Schema::connection('backup_export')->getColumnListing($table),
                        ));
                        $this->copyRows($live->table($table)->select($cols)->cursor(), $export, $table, count($cols));
                    }
                });
            } finally {
                $live->commit();
            }
        } finally {
            DB::disconnect('backup_export');
        }
    }

    /** Insert rows in batches small enough for SQLite's bound-variable limit. */
    private function copyRows(iterable $rows, \Illuminate\Database\Connection $to, string $table, int $columns): int
    {
        $size = max(1, intdiv(900, max(1, $columns)));
        $batch = [];
        $count = 0;

        foreach ($rows as $row) {
            $batch[] = (array) $row;

            if (count($batch) >= $size) {
                $to->table($table)->insert($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $to->table($table)->insert($batch);
            $count += count($batch);
        }

        return $count;
    }

    /**
     * Tables of a connection's own database. (Laravel 12 lists every schema on
     * a MySQL server unless told which one.)
     *
     * @return array<int, string>
     */
    private function tableNames(?string $connection = null): array
    {
        $conn = DB::connection($connection);
        $schema = $conn->getDriverName() === 'sqlite' ? null : $conn->getDatabaseName();

        return collect(Schema::connection($connection)->getTables($schema))
            ->pluck('name')
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->values()
            ->all();
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

        if ($pdo->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
            throw new \RuntimeException('The backup has broken links between records. Nothing was changed.');
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
     * Replace the data of every live table with the backup's rows. Works the
     * same on SQLite and MySQL/MariaDB: the backup file is read with its own
     * PDO connection and the rows are inserted through the live connection.
     *
     * @return array{tables: int, rows: int}
     */
    private function copyData(string $source): array
    {
        $src = new \PDO('sqlite:'.$source, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $srcTables = $src->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);
        $conn = DB::connection();
        $tables = 0;
        $rows = 0;

        Schema::disableForeignKeyConstraints();   // must happen outside the transaction on SQLite

        try {
            $conn->transaction(function () use ($conn, $src, $srcTables, &$tables, &$rows) {
                foreach ($this->tableNames() as $table) {
                    if (in_array($table, self::SKIP_TABLES, true)) {
                        continue;
                    }

                    $conn->table($table)->delete();

                    if (! in_array($table, $srcTables, true)) {
                        continue;
                    }

                    $quoted = '"'.str_replace('"', '""', $table).'"';
                    $srcCols = array_column($src->query("PRAGMA table_info({$quoted})")->fetchAll(\PDO::FETCH_ASSOC), 'name');
                    $cols = array_values(array_intersect(Schema::getColumnListing($table), $srcCols));

                    if ($cols === []) {
                        continue;
                    }

                    $select = implode(', ', array_map(fn ($c) => '"'.str_replace('"', '""', $c).'"', $cols));
                    $stmt = $src->query("SELECT {$select} FROM {$quoted}", \PDO::FETCH_ASSOC);
                    $rows += $this->copyRows($stmt, $conn, $table, count($cols));
                    $tables++;
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
            $src = null;
        }

        return ['tables' => $tables, 'rows' => $rows];
    }
}
