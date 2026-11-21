<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app ran with timezone UTC until 21 Nov 2026 and now runs on
 * Asia/Dhaka (UTC+6). Timestamps are stored without a zone, so every existing
 * date-time value is moved forward 6 hours to keep meaning the same moment.
 * Date-only columns (order_date, …) are left as they are.
 */
return new class extends Migration
{
    private const HOURS = 6;

    public function up(): void
    {
        $this->shift(self::HOURS);
    }

    public function down(): void
    {
        $this->shift(-self::HOURS);
    }

    private function shift(int $hours): void
    {
        $driver = DB::getDriverName();

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if ($name === 'migrations' || str_starts_with($name, 'sqlite_')) {
                continue;
            }

            $columns = collect(Schema::getColumns($name))
                ->filter(fn ($c) => in_array(strtolower($c['type_name']), ['datetime', 'timestamp'], true))
                ->pluck('name');

            foreach ($columns as $column) {
                $expr = match ($driver) {
                    'sqlite' => "datetime(\"{$column}\", '".($hours >= 0 ? '+' : '')."{$hours} hours')",
                    'mysql', 'mariadb' => "DATE_ADD(`{$column}`, INTERVAL {$hours} HOUR)",
                    'pgsql' => "\"{$column}\" + INTERVAL '{$hours} hours'",
                    default => throw new RuntimeException("Unsupported driver {$driver}"),
                };

                DB::table($name)->whereNotNull($column)->update([$column => DB::raw($expr)]);
            }
        }
    }
};
