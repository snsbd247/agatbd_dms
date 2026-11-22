<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\Delivery\DeliveryNoteItem;
use App\Models\Production\ProductionIssue;
use App\Models\Production\ProductionOrder;
use App\Models\Purchase\GoodsReceipt;
use App\Models\StockLedger;
use App\Support\Tenant;

/**
 * Where a batch came from and where it went (head office only: it looks
 * across dealers too, which is what a recall needs).
 *
 *  - backward: for a produced batch, the material batches that went into it
 *    and the supplier each came from;
 *  - forward: every delivery of the batch (to dealers and customers, and the
 *    dealers' own deliveries of it to their customers), and for a material
 *    batch the production batches it went into;
 *  - where it is now: stock left per warehouse.
 */
class BatchTrace
{
    public function trace(string $batchNo): ?array
    {
        return Tenant::bypass(function () use ($batchNo) {
            $batches = Batch::with(['product', 'warehouse.dealer'])->where('batch_no', $batchNo)->get();
            if ($batches->isEmpty()) {
                return null;
            }
            $ids = $batches->pluck('id');

            $order = ProductionOrder::with(['product', 'factory', 'issues.batch', 'issues.product'])->where('batch_no', $batchNo)->where('status', 'completed')->first();
            $inputs = $order ? $order->issues->map(fn ($i) => [
                'product' => $i->product?->name,
                'batch_no' => $i->batch?->batch_no,
                'quantity' => (float) $i->quantity,
                'supplier' => $this->supplierOf($i->batch_id),
            ])->values()->all() : [];

            $usedIn = ProductionIssue::with('order.product')->whereIn('batch_id', $ids)->get()
                ->groupBy('production_order_id')
                ->map(fn ($g) => [
                    'order' => $g->first()->order,
                    'quantity' => (float) $g->sum('quantity'),
                ])->values()->all();

            $deliveries = DeliveryNoteItem::with(['deliveryNote.salesOrder.retailer.dealer', 'deliveryNote.salesOrder.retailer.accountDealer', 'batch.warehouse.dealer'])
                ->whereIn('batch_id', $ids)->get()
                ->map(function ($item) {
                    $note = $item->deliveryNote;
                    $customer = $note?->salesOrder?->retailer;

                    return [
                        'date' => $note?->delivery_date,
                        'number' => $note?->number,
                        'seller' => $item->batch?->warehouse?->dealer?->name ?? __('Agro Tech (head office)'),
                        'customer' => $customer?->name,
                        'is_dealer' => (bool) $customer?->account_dealer_id,
                        'phone' => $customer?->phone,
                        'quantity' => (float) $item->quantity,
                    ];
                })->sortBy('date')->values()->all();

            return [
                'batch_no' => $batchNo,
                'product' => $batches->first()->product,
                'expiry' => $batches->first()->expiry_date,
                'order' => $order,
                'supplier' => $order ? null : $this->supplierOf($batches->sortBy('id')->first()->id),
                'inputs' => $inputs,
                'used_in' => $usedIn,
                'deliveries' => $deliveries,
                'stock' => $batches->map(fn ($b) => [
                    'warehouse' => $b->warehouse?->name,
                    'owner' => $b->warehouse?->dealer?->name ?? __('Agro Tech (head office)'),
                    'received' => (float) $b->quantity_received,
                    'available' => (float) $b->quantity_available,
                ])->values()->all(),
            ];
        });
    }

    /** The supplier a batch was bought from, if it came in on a goods receipt. */
    private function supplierOf(?int $batchId): ?string
    {
        if (! $batchId) {
            return null;
        }

        $receiptId = StockLedger::where('batch_id', $batchId)->where('type', 'in')
            ->where('reference_type', (new GoodsReceipt())->getMorphClass())->value('reference_id');

        return $receiptId ? GoodsReceipt::with('purchaseOrder.company')->find($receiptId)?->purchaseOrder?->company?->name : null;
    }
}
