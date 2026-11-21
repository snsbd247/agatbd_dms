<?php

namespace App\Services\Reports;

use App\Models\Batch;
use App\Models\Dealer;
use App\Models\PaymentCollection;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Support\Tenant;
use Carbon\CarbonInterface;

/**
 * Primary and secondary sales side by side (head office only).
 *
 * Primary = what each dealer bought from Agro Tech (invoices to its account).
 * Secondary = what the dealer sold on to its own customers.
 * The first row is the head office's direct sales to its own customers.
 * Used by the report page, its CSV and its PDF, so they always agree.
 */
class DealerSalesReport
{
    /** @return array<int, array<string, mixed>> */
    public function rows(CarbonInterface $start, CarbonInterface $end): array
    {
        return Tenant::bypass(function () use ($start, $end) {
            $accountIds = Retailer::whereNotNull('account_dealer_id')->pluck('id');
            $directIds = Retailer::whereNull('dealer_id')->whereNull('account_dealer_id')->pluck('id');

            $rows = [[
                'dealer' => null,
                'name' => __('Head office direct sales'),
                'bought' => null,
                'paid' => null,
                'owes' => null,
                'sold' => $this->invoiced($directIds, $start, $end),
                'collected' => $this->collected($directIds, $start, $end),
                'customers_owe' => (float) Retailer::whereIn('id', $directIds)->sum('current_due'),
                'stock_value' => null,
            ]];

            foreach (Dealer::with('account')->orderBy('name')->get() as $dealer) {
                $account = $dealer->account;
                $own = Retailer::where('dealer_id', $dealer->id)->pluck('id');
                $warehouseIds = $dealer->warehouses()->pluck('id');

                $rows[] = [
                    'dealer' => $dealer,
                    'name' => $dealer->name,
                    'bought' => $account ? $this->invoiced(collect([$account->id]), $start, $end) : 0.0,
                    'paid' => $account ? $this->collected(collect([$account->id]), $start, $end) : 0.0,
                    'owes' => (float) ($account?->current_due ?? 0),
                    'sold' => $this->invoiced($own, $start, $end),
                    'collected' => $this->collected($own, $start, $end),
                    'customers_owe' => (float) Retailer::whereIn('id', $own)->sum('current_due'),
                    'stock_value' => (float) Batch::whereIn('warehouse_id', $warehouseIds)->selectRaw('COALESCE(SUM(quantity_available * cost_price), 0) as v')->value('v'),
                ];
            }

            unset($accountIds);

            return $rows;
        });
    }

    /** @return array<string, float> */
    public function totals(array $rows): array
    {
        $sum = fn (string $key) => (float) collect($rows)->sum(fn ($r) => (float) ($r[$key] ?? 0));

        return [
            'primary' => $sum('bought'),
            'dealer_secondary' => (float) collect($rows)->filter(fn ($r) => $r['dealer'])->sum('sold'),
            'direct' => (float) ($rows[0]['sold'] ?? 0),
            'dealers_owe' => $sum('owes'),
        ];
    }

    private function invoiced($retailerIds, CarbonInterface $start, CarbonInterface $end): float
    {
        return (float) SalesInvoice::whereIn('retailer_id', $retailerIds)->whereBetween('invoice_date', [$start, $end])->sum('grand_total');
    }

    private function collected($retailerIds, CarbonInterface $start, CarbonInterface $end): float
    {
        return (float) PaymentCollection::whereIn('retailer_id', $retailerIds)->whereBetween('collection_date', [$start, $end])->sum('amount');
    }
}
