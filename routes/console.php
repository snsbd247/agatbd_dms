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
