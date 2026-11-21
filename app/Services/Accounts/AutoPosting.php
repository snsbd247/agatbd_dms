<?php

namespace App\Services\Accounts;

use App\Models\Accounts\JournalEntry;
use App\Models\Batch;
use App\Models\PaymentCollection;
use App\Models\Payroll\Advance;
use App\Models\Payroll\PayrollEntry;
use App\Models\Product;
use App\Models\Purchase\GoodsReceipt;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Retailer;
use App\Models\Returns\ReturnReceive;
use App\Models\Sales\SalesInvoice;
use App\Models\StockAdjustment;
use App\Support\Staff;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * The automatic double entries behind everyday work. Each method is called
 * by the screen that creates the document (and by accounts:backfill for old
 * documents); a document is never posted twice for the same event.
 *
 * Whose books: the business that owns the customer, warehouse or employee.
 * When the head office sells to a dealer, the dealer's books get the other
 * side too (stock in, payable to the head office).
 */
class AutoPosting
{
    public function __construct(private LedgerService $ledger) {}

    /** Delivery invoice: receivable and sale; cost of the batches that left. */
    public function salesInvoice(SalesInvoice $invoice): void
    {
        $invoice->loadMissing('items');
        $customer = $this->customer($invoice->retailer_id);
        $owner = $customer?->dealer_id;
        $net = round((float) $invoice->grand_total, 2);
        $discount = round((float) $invoice->discount, 2);
        $gross = round((float) $invoice->subtotal, 2);
        $cost = $this->costOfItems($invoice->items);

        $this->once($invoice, 'sale', fn () => [
            ['type' => 'auto', 'entry_date' => $invoice->invoice_date, 'dealer_id' => $owner,
                'narration' => __('Sales invoice :n', ['n' => $invoice->number])],
            [
                ['account' => 'ar', 'debit' => $net, 'party_type' => 'retailer', 'party_id' => $invoice->retailer_id],
                ['account' => 'sales_discount', 'debit' => $discount],
                ['account' => 'sales', 'credit' => $gross],
                ['account' => 'cogs', 'debit' => $cost],
                ['account' => 'inventory', 'credit' => $cost],
            ],
        ]);

        // A dealer's purchase from the head office, in the dealer's own books.
        if ($customer?->account_dealer_id) {
            $this->once($invoice, 'dealer_purchase', fn () => [
                ['type' => 'auto', 'entry_date' => $invoice->invoice_date, 'dealer_id' => $customer->account_dealer_id,
                    'narration' => __('Goods from Agro Tech, invoice :n', ['n' => $invoice->number])],
                [
                    ['account' => 'inventory', 'debit' => $net],
                    ['account' => 'ap', 'credit' => $net, 'party_type' => 'head_office'],
                ],
            ]);
        }
    }

    /** Money received from a customer. */
    public function collection(PaymentCollection $collection): void
    {
        $customer = $this->customer($collection->retailer_id);
        $amount = round((float) $collection->amount, 2);
        $money = $this->moneyAccount($collection->method);

        $this->once($collection, 'receipt', fn () => [
            ['type' => 'auto', 'entry_date' => $collection->collection_date, 'dealer_id' => $customer?->dealer_id,
                'narration' => __('Collection :n', ['n' => $collection->number])],
            [
                ['account' => $money, 'debit' => $amount],
                ['account' => 'ar', 'credit' => $amount, 'party_type' => 'retailer', 'party_id' => $collection->retailer_id],
            ],
        ]);

        if ($customer?->account_dealer_id) {
            $this->once($collection, 'dealer_payment', fn () => [
                ['type' => 'auto', 'entry_date' => $collection->collection_date, 'dealer_id' => $customer->account_dealer_id,
                    'narration' => __('Paid to Agro Tech, receipt :n', ['n' => $collection->number])],
                [
                    ['account' => 'ap', 'debit' => $amount, 'party_type' => 'head_office'],
                    ['account' => $money, 'credit' => $amount],
                ],
            ]);
        }
    }

    /** Goods received from a supplier: stock in, payable to the supplier. */
    public function goodsReceipt(GoodsReceipt $receipt): void
    {
        $receipt->loadMissing(['items', 'purchaseOrder']);
        $value = round((float) $receipt->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost), 2);

        $this->once($receipt, 'purchase', fn () => [
            ['type' => 'auto', 'entry_date' => $receipt->receipt_date, 'dealer_id' => Staff::dealerOfWarehouse($receipt->warehouse_id),
                'narration' => __('Goods receipt :n', ['n' => $receipt->number])],
            [
                ['account' => 'inventory', 'debit' => $value],
                ['account' => 'ap', 'credit' => $value, 'party_type' => 'company', 'party_id' => $receipt->purchaseOrder?->company_id],
            ],
        ]);
    }

    /** Goods sent back to the supplier: less stock, less payable. */
    public function purchaseReturn(PurchaseReturn $return): void
    {
        $return->loadMissing('items');
        $value = round((float) $return->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost), 2);

        $this->once($return, 'purchase_return', fn () => [
            ['type' => 'auto', 'entry_date' => $return->return_date, 'dealer_id' => Staff::dealerOfWarehouse($return->warehouse_id),
                'narration' => __('Purchase return :n', ['n' => $return->number])],
            [
                ['account' => 'ap', 'debit' => $value, 'party_type' => 'company', 'party_id' => $return->company_id],
                ['account' => 'inventory', 'credit' => $value],
            ],
        ]);
    }

    /**
     * Goods a customer returned: their receivable is credited; what went back
     * on the shelf is stock again (its cost comes off the cost of sales).
     */
    public function returnReceive(ReturnReceive $receive): void
    {
        $receive->loadMissing(['items', 'returnRequest.items']);
        $request = $receive->returnRequest;
        $customer = $this->customer($request?->retailer_id);
        $prices = $request?->items->pluck('unit_price', 'product_id') ?? collect();

        $credit = 0.0;
        $restockCost = 0.0;

        foreach ($receive->items as $item) {
            $credit += (float) $item->quantity * (float) ($prices[$item->product_id] ?? 0);

            if ($item->disposition === 'restock') {
                $cost = $item->batch_id ? (float) Tenant::bypass(fn () => Batch::whereKey($item->batch_id)->value('cost_price')) : (float) Product::whereKey($item->product_id)->value('purchase_price');
                $restockCost += (float) $item->quantity * $cost;
            }
        }

        $credit = round($credit, 2);
        $restockCost = round($restockCost, 2);

        $this->once($receive, 'sales_return', fn () => [
            ['type' => 'auto', 'entry_date' => $receive->receive_date, 'dealer_id' => $customer?->dealer_id,
                'narration' => __('Return received :n', ['n' => $receive->number])],
            [
                ['account' => 'sales_return', 'debit' => $credit],
                ['account' => 'ar', 'credit' => $credit, 'party_type' => 'retailer', 'party_id' => $request?->retailer_id],
                ['account' => 'inventory', 'debit' => $restockCost],
                ['account' => 'cogs', 'credit' => $restockCost],
            ],
        ]);

        if ($customer?->account_dealer_id) {
            $this->once($receive, 'dealer_return', fn () => [
                ['type' => 'auto', 'entry_date' => $receive->receive_date, 'dealer_id' => $customer->account_dealer_id,
                    'narration' => __('Goods sent back to Agro Tech, :n', ['n' => $receive->number])],
                [
                    ['account' => 'ap', 'debit' => $credit, 'party_type' => 'head_office'],
                    ['account' => 'inventory', 'credit' => $credit],
                ],
            ]);
        }
    }

    /** Damage, loss or count corrections. */
    public function stockAdjustment(StockAdjustment $adjustment): void
    {
        $qty = (float) $adjustment->quantity_change;
        $unitCost = $adjustment->batch_id
            ? (float) Tenant::bypass(fn () => Batch::whereKey($adjustment->batch_id)->value('cost_price'))
            : (float) Product::whereKey($adjustment->product_id)->value('purchase_price');
        $value = round(abs($qty) * $unitCost, 2);

        $lines = $qty < 0
            ? [['account' => 'stock_loss', 'debit' => $value], ['account' => 'inventory', 'credit' => $value]]
            : [['account' => 'inventory', 'debit' => $value], ['account' => 'stock_gain', 'credit' => $value]];

        $this->once($adjustment, 'adjustment', fn () => [
            ['type' => 'auto', 'entry_date' => $adjustment->adjustment_date, 'dealer_id' => Staff::dealerOfWarehouse($adjustment->warehouse_id),
                'narration' => __('Stock adjustment: :r', ['r' => $adjustment->reason])],
            $lines,
        ]);
    }

    /** Salary paid: expenses by kind; the advance taken back; the rest in cash. */
    public function payrollPaid(PayrollEntry $entry): void
    {
        if ($entry->status !== 'paid') {
            return;
        }

        $rep = Tenant::bypass(fn () => $entry->representative()->first());
        $date = $entry->paid_at ?? now();

        $this->once($entry, 'salary_paid', fn () => [
            ['type' => 'auto', 'entry_date' => $date, 'dealer_id' => $rep?->dealer_id,
                'narration' => __('Salary :n (:p)', ['n' => $entry->number, 'p' => $entry->period])],
            [
                ['account' => 'salary', 'debit' => (float) $entry->base_salary],
                ['account' => 'commission', 'debit' => (float) $entry->commission_amount],
                ['account' => 'bonus', 'debit' => (float) $entry->bonus_amount],
                ['account' => 'employee_advance', 'credit' => (float) $entry->advance_deduction, 'party_type' => 'representative', 'party_id' => $entry->representative_id],
                ['account' => 'other_income', 'credit' => (float) $entry->other_deduction],
                ['account' => 'cash', 'credit' => (float) $entry->net_salary],
            ],
        ]);
    }

    /** Cash advance to an employee. */
    public function advance(Advance $advance): void
    {
        $rep = Tenant::bypass(fn () => $advance->representative()->first());
        $amount = round((float) $advance->amount, 2);

        $this->once($advance, 'advance', fn () => [
            ['type' => 'auto', 'entry_date' => $advance->advance_date, 'dealer_id' => $rep?->dealer_id,
                'narration' => __('Advance to :name', ['name' => $rep?->name])],
            [
                ['account' => 'employee_advance', 'debit' => $amount, 'party_type' => 'representative', 'party_id' => $advance->representative_id],
                ['account' => 'cash', 'credit' => $amount],
            ],
        ]);
    }

    /** Cash, bank or mobile money account for a payment method. */
    public function moneyAccount(?string $method): string
    {
        return match ($method) {
            'bank' => 'bank',
            'mobile' => 'mobile',
            default => 'cash',
        };
    }

    /**
     * Post once per (document, event). Nothing is posted when every amount is
     * zero (e.g. a free sample).
     *
     * @param  callable(): array{0: array, 1: array}  $build
     */
    private function once(Model $source, string $event, callable $build): void
    {
        $exists = Tenant::bypass(fn () => JournalEntry::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('source_event', $event)
            ->exists());

        if ($exists) {
            return;
        }

        [$header, $lines] = $build();

        $lines = array_values(array_filter($lines, fn ($l) => round((float) ($l['debit'] ?? 0), 2) != 0 || round((float) ($l['credit'] ?? 0), 2) != 0));

        if (count($lines) < 2) {
            return;
        }

        $this->ledger->post($header + ['source' => $source, 'source_event' => $event], $lines);
    }

    private function customer(?int $retailerId): ?Retailer
    {
        return $retailerId ? Tenant::bypass(fn () => Retailer::find($retailerId)) : null;
    }

    private function costOfItems($items): float
    {
        $batchCosts = Tenant::bypass(fn () => Batch::whereIn('id', $items->pluck('batch_id')->filter())->pluck('cost_price', 'id'));

        return round((float) $items->sum(function ($item) use ($batchCosts) {
            $cost = $item->batch_id && isset($batchCosts[$item->batch_id])
                ? (float) $batchCosts[$item->batch_id]
                : (float) Product::whereKey($item->product_id)->value('purchase_price');

            return (float) $item->quantity * $cost;
        }), 2);
    }
}
