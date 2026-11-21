<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-step move from the SQLite file to MySQL/MariaDB.
 *
 *   php artisan dms:copy-to-mysql --database=dms
 *
 * Creates the database if needed, builds the schema with the normal
 * migrations, copies every row from the current SQLite database and then
 * compares row counts table by table. The running app is not touched: it
 * keeps using SQLite until .env is switched (see development.md).
 */
class CopyToMysql extends Command
{
    protected $signature = 'dms:copy-to-mysql
        {--host=127.0.0.1} {--port=3306} {--database=dms} {--username=root} {--password=}
        {--fresh : Drop and rebuild the target tables if the database is not empty}';

    protected $description = 'Copy all DMS data from SQLite into a MySQL/MariaDB database and verify it';

    private const SKIP = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->error('The app must still be on SQLite (the source) when running this command.');

            return self::FAILURE;
        }

        $name = (string) $this->option('database');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $this->error('Database name may only contain letters, numbers and _.');

            return self::FAILURE;
        }

        $base = [
            'driver' => 'mysql',
            'host' => $this->option('host'),
            'port' => $this->option('port'),
            'username' => $this->option('username'),
            'password' => (string) $this->option('password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ];

        config(['database.connections.mysql_server' => $base + ['database' => null]]);
        DB::connection('mysql_server')->statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        config(['database.connections.mysql_copy' => $base + ['database' => $name]]);
        $target = DB::connection('mysql_copy');

        $existing = Schema::connection('mysql_copy')->getTables($name);   // Laravel lists every database on the server unless told which

        if ($existing !== [] && ! $this->option('fresh')) {
            $this->error("Database `{$name}` already has tables. Use --fresh to rebuild it (this deletes its data).");

            return self::FAILURE;
        }

        $this->info("Building schema in `{$name}`…");
        $this->call($existing === [] ? 'migrate' : 'migrate:fresh', ['--database' => 'mysql_copy', '--force' => true]);

        $source = DB::connection();
        $tables = collect(Schema::getTables())->pluck('name')
            ->reject(fn ($t) => in_array($t, self::SKIP, true) || str_starts_with($t, 'sqlite_'))
            ->values();
        $targetTables = collect(Schema::connection('mysql_copy')->getTables($name))->pluck('name')->all();

        $target->statement('SET FOREIGN_KEY_CHECKS = 0');
        $rows = [];

        try {
            foreach ($tables as $table) {
                if (! in_array($table, $targetTables, true)) {
                    $this->warn("Skipping {$table}: not in the target schema.");

                    continue;
                }

                $columns = collect(Schema::getColumnListing($table))
                    ->intersect(Schema::connection('mysql_copy')->getColumnListing($table))
                    ->values()
                    ->all();

                $target->table($table)->truncate();

                $batch = [];
                $count = 0;

                foreach ($source->table($table)->select($columns)->cursor() as $row) {
                    $batch[] = (array) $row;

                    if (count($batch) === 500) {
                        $target->table($table)->insert($batch);
                        $count += count($batch);
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    $target->table($table)->insert($batch);
                    $count += count($batch);
                }

                $rows[$table] = $count;
            }
        } finally {
            $target->statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->info('Verifying…');
        $problems = [];

        foreach ($rows as $table => $count) {
            $inSource = $source->table($table)->count();
            $inTarget = $target->table($table)->count();

            if ($inSource !== $inTarget) {
                $problems[] = [$table, $inSource, $inTarget];
            }
        }

        if ($problems !== []) {
            $this->table(['Table', 'SQLite rows', 'MySQL rows'], $problems);
            $this->error('Row counts differ — do not switch to MySQL.');

            return self::FAILURE;
        }

        $this->info(sprintf('Copied %d rows in %d tables into `%s`; all row counts match.', array_sum($rows), count($rows), $name));
        $this->line('To switch the app: set DB_CONNECTION=mysql and DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD in .env, then run php artisan config:cache.');

        return self::SUCCESS;
    }
}
