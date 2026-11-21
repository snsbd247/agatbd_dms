<?php

namespace Database\Seeders;

use App\Console\Commands\AccountsBackfill;
use App\Services\Accounts\AutoPosting;
use Illuminate\Database\Seeder;

/** After the demo data: post its accounting entries (same as accounts:backfill). */
class AccountingBackfillSeeder extends Seeder
{
    public function run(): void
    {
        AccountsBackfill::postAll(app(AutoPosting::class));
    }
}
