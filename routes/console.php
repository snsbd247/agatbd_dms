<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Approved cost sheets whose date has come become the products' prices (cron runs schedule:run every minute).
Artisan::command('costing:apply-due', function () {
    $this->info(app(\App\Services\Costing\CostingService::class)->applyDue().' cost sheet(s) applied.');
})->purpose('Apply approved cost sheets whose effective date has come');

\Illuminate\Support\Facades\Schedule::command('costing:apply-due')->dailyAt('00:05');

// Clear demo/test data before going live (a backup is taken first). Same as the button on Backup & Restore.
Artisan::command('dms:clear-data {mode : transactions or all} {--force}', function (string $mode) {
    if (! in_array($mode, ['transactions', 'all'], true)) {
        $this->error('Mode must be "transactions" or "all".');

        return 1;
    }
    if (! $this->option('force') && ! $this->confirm("Clear $mode data? A backup is taken first.")) {
        return 1;
    }
    $file = app(\App\Services\BackupService::class)->create('before-clear');
    $removed = app(\App\Services\DataResetService::class)->reset($mode);
    $this->info('Backup: '.basename($file));
    $this->info(array_sum($removed).' rows removed from '.count($removed).' tables.');

    return 0;
})->purpose('Clear demo or test data (transactions only, or all business data)');
