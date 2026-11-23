<?php

namespace App\Services;

use App\Models\Accounts\JournalEntry;
use App\Models\Batch;
use App\Models\PaymentCollection;
use App\Models\Purchase\GoodsReceipt;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Models\Settings\AuditLog;
use App\Models\StockAdjustment;
use App\Models\StockLedger;
use App\Services\Accounts\LedgerService;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cancelling a posted document. Posted documents are never edited: a void
 * undoes every effect (stock, customer due, invoice payments) and reverses
 * its voucher with a dated reversal entry, so the books show both. The
 * document itself is removed and the audit log keeps what it was, who
 * voided it, when and why.
 */
class VoidService
{
    public function __construct(
        private LedgerService $ledger,
        private InventoryService $inventory,
    ) {}

    /** A collection: the customer owes the money again and the invoices it paid are unpaid again. */
    public function collection(PaymentCollection $c, string $reason): void
    {
        DB::transaction(function () use ($c, $reason) {
            $retailer = Retailer::whereKey($c->retailer_id)->lockForUpdate()->firstOrFail();
            $amount = (float) $c->amount;

            // Collections pay the oldest invoices first; undo from the newest paid.
            $left = $amount;
            $invoices = SalesInvoice::where('retailer_id', $retailer->id)->where('paid_amount', '>', 0)
                ->orderByDesc('invoice_date')->orderByDesc('id')->lockForUpdate()->get();
            foreach ($invoices as $inv) {
                if ($left <= 0.004) {
                    break;
                }
                $back = min((float) $inv->paid_amount, $left);
                $inv->paid_amount = round((float) $inv->paid_amount - $back, 2);
                $inv->due_amount = round((float) $inv->due_amount + $back, 2);
                $inv->status = $inv->paid_amount <= 0 ? 'unpaid' : 'partial';
                $inv->save();
                $left -= $back;
            }

            $retailer->increment('current_due', $amount);
            $retailer->ledgerEntries()->create([
                'type' => 'collection_void',
                'reference_type' => $c->getMorphClass(),
                'reference_id' => $c->getKey(),
                'debit' => $amount,
                'credit' => 0,
                'balance_after' => $retailer->current_due,
                'entry_date' => today(),
            ]);

            $this->reverseVouchers($c, __('Collection :n voided', ['n' => $c->number]).': '.$reason);
            $this->log('collection', $c, $reason);
            $c->delete();
        });
    }

    /** A stock adjustment: the opposite movement, and the original voucher reversed. */
    public function adjustment(StockAdjustment $a, string $reason): void
    {
        if (str_starts_with((string) $a->reason, 'VOID')) {
            throw new \RuntimeException(__('A reversal cannot be voided.'));
        }
        if (StockAdjustment::where('reason', 'like', 'VOID #'.$a->id.':%')->exists()) {
            throw new \RuntimeException(__('This adjustment has already been voided.'));
        }
        if (! $this->vouchersOf($a)->exists() && (float) $a->quantity_change > 0 && $a->reason === __('Opening stock')) {
            throw new \RuntimeException(__('Opening stock is part of the opening balances. Correct it with a new adjustment instead.'));
        }

        DB::transaction(function () use ($a, $reason) {
            $this->inventory->adjustStock(
                warehouseId: $a->warehouse_id,
                productId: $a->product_id,
                batchId: $a->batch_id,
                change: -1 * (float) $a->quantity_change,
                reason: 'VOID #'.$a->id.': '.$reason,
                userId: Auth::id(),
                date: today()->toDateString(),
                post: false,
            );
            $this->reverseVouchers($a, __('Stock adjustment voided').': '.$reason);
            $this->log('stock_adjustment', $a, $reason);
        });
    }

    /**
     * A goods receipt, only while none of its stock has been used: the
     * batches come out again, the purchase order is open for that quantity,
     * and the supplier payable is reversed.
     */
    public function goodsReceipt(GoodsReceipt $r, string $reason): void
    {
        $moves = StockLedger::where('reference_type', $r->getMorphClass())->where('reference_id', $r->id)->where('type', 'in')->get();
        foreach ($moves as $m) {
            $batch = Tenant::bypass(fn () => Batch::find($m->batch_id));
            if (! $batch || bccomp((string) $batch->quantity_available, (string) $m->quantity, 3) < 0) {
                throw new \RuntimeException(__('Some of this stock has already been sold, moved or used. Return it to the supplier with a purchase return instead.'));
            }
        }

        DB::transaction(function () use ($r, $moves, $reason) {
            foreach ($moves as $m) {
                $this->inventory->takeFromBatch($m->batch_id, (float) $m->quantity, $r);
            }

            $po = $r->purchaseOrder;
            if ($po) {
                foreach ($r->items as $item) {
                    $line = $po->items()->where('product_id', $item->product_id)->where('received_quantity', '>', 0)->first();
                    $line?->update(['received_quantity' => max(0, round((float) $line->received_quantity - (float) $item->quantity, 3))]);
                }
                $any = $po->items()->where('received_quantity', '>', 0)->exists();
                $all = $po->items()->whereColumn('received_quantity', '<', 'quantity')->doesntExist();
                $po->update(['status' => $all ? 'completed' : ($any ? 'partial' : 'sent')]);
            }

            $this->reverseVouchers($r, __('Goods receipt :n voided', ['n' => $r->number]).': '.$reason);
            $this->log('goods_receipt', $r, $reason);
            $r->items()->delete();
            $r->delete();
        });
    }

    /** Delete a record that has auto-posted vouchers: the vouchers are reversed first. */
    public function deleteWithReversal(Model $record, string $type, string $reason): void
    {
        DB::transaction(function () use ($record, $type, $reason) {
            $this->reverseVouchers($record, $reason);
            $this->log($type, $record, $reason);
            $record->delete();
        });
    }

    private function vouchersOf(Model $source)
    {
        return Tenant::bypass(fn () => JournalEntry::where('source_type', $source->getMorphClass())->where('source_id', $source->getKey()));
    }

    private function reverseVouchers(Model $source, string $reason): void
    {
        foreach (Tenant::bypass(fn () => $this->vouchersOf($source)->where('status', 'posted')->whereNull('reversal_of_id')->with('lines')->get()) as $entry) {
            if (! $entry->reversedBy()->exists()) {
                $this->ledger->reverse($entry, today()->toDateString(), $reason);
            }
        }
    }

    private function log(string $type, Model $record, string $reason): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $type.'.voided',
            'new_values' => ['reason' => $reason, 'record' => $record->toArray()],
            'ip_address' => request()?->ip(),
        ]);
    }
}
