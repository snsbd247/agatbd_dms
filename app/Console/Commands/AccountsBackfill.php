<?php

namespace App\Console\Commands;

use App\Models\PaymentCollection;
use App\Models\Payroll\Advance;
use App\Models\Payroll\PayrollEntry;
use App\Models\Purchase\GoodsReceipt;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Returns\ReturnReceive;
use App\Models\Sales\SalesInvoice;
use App\Models\StockAdjustment;
use App\Services\Accounts\AutoPosting;
use App\Support\Tenant;
use Illuminate\Console\Command;

/**
 * Posts the accounting entries of documents made before accounts existed (or
 * by seeders). Safe to run again: a document is never posted twice.
 *
 *   php artisan accounts:backfill
 */
class AccountsBackfill extends Command
{
    protected $signature = 'accounts:backfill';

    protected $description = 'Post accounting entries for existing sales, purchases, collections, returns, adjustments and payroll';

    public function handle(AutoPosting $posting): int
    {
        $counts = Tenant::bypass(fn () => self::postAll($posting));

        foreach ($counts as $what => $n) {
            $this->line(str_pad($what, 18).$n);
        }
        $this->info('Done.');

        return self::SUCCESS;
    }

    /** @return array<string, int> documents looked at, per kind */
    public static function postAll(AutoPosting $posting): array
    {
        $jobs = [
            'goods receipts' => [GoodsReceipt::query()->orderBy('receipt_date')->orderBy('id'), 'goodsReceipt'],
            'sales invoices' => [SalesInvoice::query()->orderBy('invoice_date')->orderBy('id'), 'salesInvoice'],
            'collections' => [PaymentCollection::query()->orderBy('collection_date')->orderBy('id'), 'collection'],
            'return receipts' => [ReturnReceive::query()->orderBy('receive_date')->orderBy('id'), 'returnReceive'],
            'purchase returns' => [PurchaseReturn::query()->whereIn('status', ['sent', 'completed'])->orderBy('return_date')->orderBy('id'), 'purchaseReturn'],
            'adjustments' => [StockAdjustment::query()->orderBy('adjustment_date')->orderBy('id'), 'stockAdjustment'],
            'advances' => [Advance::query()->orderBy('advance_date')->orderBy('id'), 'advance'],
            'salaries paid' => [PayrollEntry::query()->where('status', 'paid')->orderBy('paid_at')->orderBy('id'), 'payrollPaid'],
        ];

        $counts = [];

        foreach ($jobs as $label => [$query, $method]) {
            $counts[$label] = 0;
            foreach ($query->cursor() as $document) {
                $posting->{$method}($document);
                $counts[$label]++;
            }
        }

        return $counts;
    }
}
