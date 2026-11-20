<?php

namespace App\Services;

use App\Models\Delivery\DeliveryInvoice;
use App\Models\Delivery\DeliveryInvoiceItem;
use App\Models\Delivery\DeliveryNote;
use App\Models\Delivery\DeliveryNoteItem;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the printable invoice for one delivery note.
 *
 * Stock movement, the sales invoice, the retailer ledger and the sales order
 * status are all handled when the delivery note itself is created
 * (App\Livewire\Delivery\Create). This service only snapshots the delivered
 * lines and their prices into a delivery invoice, so it never touches stock.
 */
class DeliveryInvoiceService
{
    public function __construct(private NumberGeneratorService $numbers)
    {
    }

    public function createDeliveryInvoice(DeliveryNote $deliveryNote): DeliveryInvoice
    {
        return DB::transaction(function () use ($deliveryNote) {
            if ($deliveryNote->invoices()->exists()) {
                throw new \RuntimeException('An invoice already exists for this delivery note.');
            }

            $deliveryItems = $deliveryNote->items()->with('salesOrderItem')->get();

            if ($deliveryItems->isEmpty()) {
                throw new \RuntimeException('This delivery note has no items.');
            }

            $invoice = DeliveryInvoice::create([
                'number' => $this->numbers->generate('delivery_invoice'),
                'sales_order_id' => $deliveryNote->sales_order_id,
                'delivery_note_id' => $deliveryNote->id,
                'retailer_id' => $deliveryNote->salesOrder->retailer_id,
                'invoice_date' => $deliveryNote->delivery_date ?? today(),
                'status' => 'pending',
            ]);

            $subtotal = 0;

            foreach ($deliveryItems as $deliveryItem) {
                $unitPrice = (float) ($deliveryItem->salesOrderItem->unit_price ?? 0);
                $totalPrice = round((float) $deliveryItem->quantity * $unitPrice, 2);

                DeliveryInvoiceItem::create([
                    'delivery_invoice_id' => $invoice->id,
                    'sales_order_item_id' => $deliveryItem->sales_order_item_id,
                    'product_id' => $deliveryItem->product_id,
                    'batch_id' => $deliveryItem->batch_id,
                    'quantity' => $deliveryItem->quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);

                $subtotal += $totalPrice;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount' => 0,
                'grand_total' => $subtotal,
            ]);

            return $invoice;
        });
    }

    /**
     * Ordered / delivered / remaining quantity per sales order line.
     */
    public function getPartialDeliveryInfo(SalesOrder $salesOrder): array
    {
        return $salesOrder->items()->with('product')->get()->map(function ($item) {
            $delivered = (float) DeliveryNoteItem::where('sales_order_item_id', $item->id)->sum('quantity');

            return [
                'item_id' => $item->id,
                'product_name' => $item->product->name ?? 'N/A',
                'ordered_qty' => (float) $item->quantity,
                'delivered_qty' => $delivered,
                'remaining_qty' => max(0, (float) $item->quantity - $delivered),
            ];
        })->all();
    }

    public function finalizeInvoice(DeliveryInvoice $invoice): bool
    {
        if ($invoice->status !== 'pending') {
            return false;
        }

        return $invoice->update(['status' => 'finalized']);
    }

    public function markAsPrinted(DeliveryInvoice $invoice): bool
    {
        return $invoice->update(['status' => 'printed']);
    }
}
