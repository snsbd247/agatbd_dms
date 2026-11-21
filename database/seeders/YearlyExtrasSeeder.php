<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Delivery\DeliveryNote;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Returns\CompanyReturn;
use App\Models\Returns\CompanyReturnItem;
use App\Models\Returns\ReturnReceive;
use App\Models\Returns\ReturnReceiveItem;
use App\Models\Returns\ReturnRequest;
use App\Models\Returns\ReturnRequestItem;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Warranty\WarrantyClaim;
use App\Models\Warranty\WarrantyPolicy;
use App\Services\DeliveryInvoiceService;
use App\Services\InventoryService;
use App\Services\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Fills the modules YearlyDemoSeeder leaves empty, over the same twelve
 * months: delivery invoices, stock adjustments, warranty policies and claim
 * history, damaged returns sent back to the company, and rejected / pending
 * return requests. Run it after YearlyDemoSeeder; it skips itself when
 * delivery invoices already exist.
 */
class YearlyExtrasSeeder extends Seeder
{
    private NumberGeneratorService $numbers;

    private InventoryService $inventory;

    private Warehouse $warehouse;

    private User $dealer;

    public function run(): void
    {
        if (\App\Models\Delivery\DeliveryInvoice::count() > 0) {
            $this->command?->info('Extra demo data already present, skipping.');

            return;
        }

        $this->numbers = app(NumberGeneratorService::class);
        $this->inventory = app(InventoryService::class);
        $this->warehouse = Warehouse::where('name', 'Central Warehouse')->firstOrFail();
        $this->dealer = User::where('email', 'admin@dms.local')->firstOrFail();

        $this->seedWarrantyPolicies();
        $this->seedDeliveryInvoices();

        for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
            $monthIndex = 11 - $monthsAgo;
            $monthStart = today()->subMonths($monthsAgo)->startOfMonth();

            $this->seedAdjustments($monthIndex, $monthStart);
            $this->seedExtraReturn($monthIndex, $monthStart);
            $this->seedExtraWarrantyClaim($monthIndex, $monthStart);
        }

        $this->seedWarrantyTracking();
        $this->settleCompanyReturns();

        $this->command?->info('Delivery invoices, adjustments, company returns and warranty history seeded.');
    }

    private function seedWarrantyPolicies(): void
    {
        $months = ['Personal Care' => 6, 'Home Care' => 12];

        foreach (Category::all() as $category) {
            WarrantyPolicy::firstOrCreate(
                ['category_id' => $category->id, 'product_id' => null],
                [
                    'duration_months' => $months[$category->name] ?? 3,
                    'terms' => 'Manufacturing defects only. Bring the product with its invoice. Physical or water damage is not covered.',
                ]
            );
        }

        // One product with its own, longer policy, so both kinds show up.
        if ($product = Product::where('sku', 'SAVLON-HW-250')->first()) {
            WarrantyPolicy::firstOrCreate(
                ['product_id' => $product->id],
                ['duration_months' => 18, 'terms' => 'Pump and bottle replaced free for 18 months.']
            );
        }
    }

    /** One delivery invoice per delivery note, as the delivery screen creates them. */
    private function seedDeliveryInvoices(): void
    {
        $service = app(DeliveryInvoiceService::class);

        DeliveryNote::with('salesOrder')->orderBy('delivery_date')->orderBy('id')->get()
            ->each(function (DeliveryNote $note) use ($service) {
                $invoice = $service->createDeliveryInvoice($note);
                $invoice->update(['status' => match (true) {
                    Carbon::parse($note->delivery_date)->isSameMonth(today()) => 'pending',
                    $note->id % 3 === 0 => 'finalized',
                    default => 'printed',
                }]);
            });
    }

    /** A damaged-goods write-off every month and a stock-count correction every other month. */
    private function seedAdjustments(int $monthIndex, Carbon $monthStart): void
    {
        $date = $monthStart->copy()->addDays(24);

        if ($date->isFuture()) {
            return;
        }

        // Batches bought this month ("SKU-B<month>", received on the 4th), so the
        // adjustment never predates the stock it touches.
        $batches = Batch::where('warehouse_id', $this->warehouse->id)
            ->where('quantity_available', '>=', 5)
            ->where('batch_no', 'like', '%-B'.($monthIndex + 1))
            ->orderBy('id')
            ->get();

        if ($batches->isEmpty()) {
            return;
        }

        $batch = $batches[$monthIndex % $batches->count()];
        $reasons = ['Damaged in store room', 'Expired items removed', 'Broken during loading', 'Rat damage'];

        $this->inventory->adjustStock(
            warehouseId: $this->warehouse->id,
            productId: $batch->product_id,
            batchId: $batch->id,
            change: -2,
            reason: $reasons[$monthIndex % count($reasons)],
            userId: $this->dealer->id,
            date: $date->toDateString(),
        );

        if ($monthIndex % 2 === 1) {
            $other = $batches[($monthIndex + 3) % $batches->count()];
            $this->inventory->adjustStock(
                warehouseId: $this->warehouse->id,
                productId: $other->product_id,
                batchId: $other->id,
                change: 1,
                reason: 'Stock count correction',
                userId: $this->dealer->id,
                date: $date->toDateString(),
            );
        }
    }

    /**
     * YearlyDemoSeeder adds a restocked return every third month. The other
     * months get: damaged goods sent back to the company, a rejected request,
     * or (this month) one still waiting for approval.
     */
    private function seedExtraReturn(int $monthIndex, Carbon $monthStart): void
    {
        if ($monthIndex % 3 === 0) {
            return;
        }

        $requestDate = $monthStart->copy()->addDays(17);
        $isCurrentMonth = $monthStart->isSameMonth(today());

        if ($requestDate->isFuture()) {
            $requestDate = today();
        }

        $retailers = Retailer::orderBy('id')->get();
        $retailer = $retailers[($monthIndex + 1) % $retailers->count()];
        $line = $this->boughtLine($retailer, $monthStart, $requestDate);

        if (! $line) {
            return;
        }

        $kind = $isCurrentMonth ? 'pending' : ($monthIndex % 3 === 1 ? 'company_return' : 'rejected');
        $qty = 2;

        // Never credit a retailer past what they owe: a credit balance would look like a bug in the demo.
        if ($kind === 'company_return' && $qty * (float) $line->unit_price > (float) $retailer->fresh()->current_due) {
            $kind = 'rejected';
        }

        $request = ReturnRequest::create([
            'number' => $this->numbers->generate('return_request'),
            'retailer_id' => $retailer->id,
            'requested_by' => $retailer->representative?->user_id,
            'request_date' => $requestDate,
            'status' => $kind === 'company_return' ? 'approved' : $kind,
            'reason' => $kind === 'rejected' ? 'Customer changed mind' : 'Manufacturing defect',
        ]);
        ReturnRequestItem::create([
            'return_request_id' => $request->id,
            'product_id' => $line->product_id,
            'quantity' => $qty,
            'unit_price' => $line->unit_price,
            'reason' => $kind === 'rejected' ? 'Not damaged' : 'Leaking / broken seal',
        ]);

        if ($kind !== 'company_return') {
            return;
        }

        $receiveDate = $requestDate->copy()->addDay()->min(today());
        $receive = ReturnReceive::create([
            'number' => $this->numbers->generate('return_receive'),
            'return_request_id' => $request->id,
            'received_by' => $this->dealer->id,
            'warehouse_id' => $this->warehouse->id,
            'receive_date' => $receiveDate,
        ]);
        ReturnReceiveItem::create([
            'return_receive_id' => $receive->id,
            'product_id' => $line->product_id,
            'batch_id' => null,
            'quantity' => $qty,
            'disposition' => 'company_return',
        ]);

        // Same grouping as the receive screen: one draft per company until it is sent.
        $product = Product::find($line->product_id);
        $companyReturn = CompanyReturn::firstOrCreate(
            ['company_id' => $product->company_id, 'warehouse_id' => $this->warehouse->id, 'status' => 'draft'],
            ['number' => $this->numbers->generate('company_return'), 'return_date' => $receiveDate]
        );
        $item = CompanyReturnItem::firstOrNew(['company_return_id' => $companyReturn->id, 'product_id' => $product->id]);
        $item->quantity = (float) $item->quantity + $qty;
        $item->save();

        $credit = $qty * (float) $line->unit_price;
        $locked = Retailer::whereKey($retailer->id)->lockForUpdate()->first();
        $locked->decrement('current_due', $credit);
        $locked->ledgerEntries()->create([
            'type' => 'return',
            'reference_type' => $receive->getMorphClass(),
            'reference_id' => $receive->getKey(),
            'debit' => 0,
            'credit' => $credit,
            'balance_after' => $locked->current_due,
            'entry_date' => $receiveDate,
        ]);
        $request->update(['status' => 'received']);

        // Damaged goods go back to the company about once a quarter: older drafts are sent.
        // The latest one stays a draft, so the "send to company" step can be tried.
        if ($monthIndex < 10) {
            $companyReturn->update(['status' => 'sent', 'return_date' => $receiveDate->copy()->addDays(5)->min(today())]);
        }
    }

    /** A claim in every month that YearlyDemoSeeder leaves without one. */
    private function seedExtraWarrantyClaim(int $monthIndex, Carbon $monthStart): void
    {
        if ($monthIndex % 4 === 0) {
            return;
        }

        $claimDate = $monthStart->copy()->addDays(21);

        if ($claimDate->isFuture()) {
            $claimDate = today();
        }

        $retailers = Retailer::orderBy('id')->get();
        $retailer = $retailers[($monthIndex + 2) % $retailers->count()];
        $line = $this->boughtLine($retailer, $monthStart->copy()->subMonth(), $claimDate);

        if (! $line) {
            return;
        }

        $statuses = ['repaired', 'replaced', 'rejected', 'approved'];
        $status = $monthStart->isSameMonth(today()) ? 'pending' : $statuses[$monthIndex % count($statuses)];
        $issues = ['Pump not working', 'Seal broken on delivery', 'Product discoloured before expiry', 'Packaging leaking'];

        WarrantyClaim::create([
            'number' => $this->numbers->generate('warranty_claim'),
            'retailer_id' => $retailer->id,
            'product_id' => $line->product_id,
            'sales_invoice_id' => $line->sales_invoice_id,
            'claim_date' => $claimDate,
            'issue_description' => $issues[$monthIndex % count($issues)],
            'status' => $status,
        ]);
    }

    /** Status history for every claim, matching the transitions the claim screen allows. */
    private function seedWarrantyTracking(): void
    {
        foreach (WarrantyClaim::doesntHave('tracking')->get() as $claim) {
            $at = Carbon::parse($claim->claim_date)->setTime(10, 0);
            $steps = [['pending', 'Claim received', $at]];

            if (in_array($claim->status, ['approved', 'repaired', 'replaced'], true)) {
                $steps[] = ['approved', 'Checked: covered by warranty', $at->copy()->addDays(2)];
            }
            if ($claim->status === 'rejected') {
                $steps[] = ['rejected', 'Outside warranty / physical damage', $at->copy()->addDays(2)];
            }
            if (in_array($claim->status, ['repaired', 'replaced'], true)) {
                $steps[] = [$claim->status, $claim->status === 'replaced' ? 'New unit handed over' : 'Repaired and returned', $at->copy()->addDays(6)];
            }

            foreach ($steps as [$status, $note, $when]) {
                $claim->tracking()->create([
                    'status' => $status,
                    'note' => $note,
                    'changed_by' => $status === 'pending' ? null : $this->dealer->id,
                    'changed_at' => $when->min(now()),
                ]);
            }
        }
    }

    /** Returns sent more than a month ago have been settled by the company. */
    private function settleCompanyReturns(): void
    {
        CompanyReturn::where('status', 'sent')
            ->whereDate('return_date', '<', today()->subMonth())
            ->update(['status' => 'completed']);
    }

    /** A product the retailer was invoiced for in the given window. */
    private function boughtLine(Retailer $retailer, Carbon $from, Carbon $to): ?SalesInvoiceItem
    {
        return SalesInvoiceItem::query()
            ->whereIn('sales_invoice_id', SalesInvoice::where('retailer_id', $retailer->id)
                ->whereBetween('invoice_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->select('id'))
            ->orderBy('unit_price')
            ->orderBy('id')
            ->first();
    }
}
