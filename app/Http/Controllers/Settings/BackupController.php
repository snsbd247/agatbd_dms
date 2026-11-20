<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupController extends Controller
{
    private const EXCLUDED_TABLES = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'sessions', 'password_reset_tokens',
    ];

    public function download()
    {
        $tables = collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn ($name) => in_array($name, self::EXCLUDED_TABLES, true))
            ->values();

        $filename = 'dms-backup-'.today()->toDateString().'.json';

        return response()->streamDownload(function () use ($tables) {
            $out = fopen('php://output', 'w');
            fwrite($out, '{');
            foreach ($tables as $index => $table) {
                if ($index > 0) {
                    fwrite($out, ',');
                }
                fwrite($out, json_encode($table).':');
                fwrite($out, DB::table($table)->get()->toJson());
            }
            fwrite($out, '}');
            fclose($out);
        }, $filename, ['Content-Type' => 'application/json']);
    }
}
