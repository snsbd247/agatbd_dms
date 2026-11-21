<?php

namespace App\Livewire\Delivery;

use App\Models\Delivery\DeliveryNote;
use App\Models\Delivery\DeliveryNoteItem;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceItem;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\Stock;
use App\Services\DeliveryInvoiceService;
use App\Services\InventoryService;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Deliver Order')]
class Create extends Component
{
    public SalesOrder $salesOrder;

    public array $lines = [];
    public string $delivery_type = 'dealer';

    public function mount(): void
    {
        $this->authorize('delivery.manage');

        abort_unless(in_array($this->salesOrder->status, ['approved', 'partial']), 403);

        $this->salesOrder->load('items.product');

        $this->lines = $this->salesOrder->items
            ->map(function (SalesOrderItem $item) {
                $remaining = (float) $item->quantity - (float) $item->delivered_quantity;
                $available = (float) (Stock::where('warehouse_id', $this->salesOrder->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->value('quantity') ?? 0);
                $deliverable = min($remaining, $available);

                return [
                    'item_id' => $item->id,
                    'product_name' => $item->product->name,
                    'remaining' => $remaining,
                    'available' => $available,
                    'quantity' => $deliverable > 0 ? $deliverable : 0,
                ];
            })
            ->filter(fn ($line) => $line['remaining'] > 0)
            ->values()
            ->toArray();
    }

    public function save(NumberGeneratorService $numbers, InventoryService $inventory, DeliveryInvoiceService $deliveryInvoices): void
    {
        $this->authorize('delivery.manage');

        $hasAny = false;

        foreach ($this->lines as $index => $line) {
            $qty = (float) ($line['quantity'] ?: 0);
            $cap = min((float) $line['remaining'], (float) $line['available']);

            if ($qty < 0) {
                $this->addError("lines.$index.quantity", 'Quantity cannot be negative.');
            } elseif ($qty > $cap) {
                $this->addError("lines.$index.quantity", 'Cannot deliver more than the remaining ordered quantity or available stock.');
            } elseif ($qty > 0) {
                $hasAny = true;
            }
        }

        if (! $hasAny) {
            $this->addError('lines', 'Enter a quantity for at least one item.');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $smsInvoice = null;

        DB::transaction(function () use ($numbers, $inventory, $deliveryInvoices, &$smsInvoice) {
            $deliveryNote = DeliveryNote::create([
                'number' => $numbers->generate('delivery_note'),
                'sales_order_id' => $this->salesOrder->id,
                'delivery_type' => $this->delivery_type,
                'delivered_by' => Auth::id(),
                'delivery_date' => today(),
                'status' => 'delivered',
            ]);

            $invoiceTotal = 0;
            $invoiceLineData = [];

            foreach ($this->lines as $line) {
                $qty = (float) $line['quantity'];

                if ($qty <= 0) {
                    continue;
                }

                $item = SalesOrderItem::findOrFail($line['item_id']);

                $consumed = $inventory->consumeStock(
                    productId: $item->product_id,
                    warehouseId: $this->salesOrder->warehouse_id,
                    quantity: $qty,
                    reference: $deliveryNote,
                );

                foreach ($consumed as $split) {
                    DeliveryNoteItem::create([
                        'delivery_note_id' => $deliveryNote->id,
                        'sales_order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'batch_id' => $split['batch_id'],
                        'quantity' => $split['quantity'],
                    ]);

                    $lineTotal = $split['quantity'] * $item->unit_price;
                    $invoiceTotal += $lineTotal;
                    $invoiceLineData[] = [
                        'product_id' => $item->product_id,
                        'batch_id' => $split['batch_id'],
                        'quantity' => $split['quantity'],
                        'unit_price' => $item->unit_price,
                        'line_total' => $lineTotal,
                    ];
                }

                $item->increment('delivered_quantity', $qty);
            }

            $isFinalDelivery = $this->salesOrder->items()->whereColumn('delivered_quantity', '<', 'quantity')->doesntExist();
            $discount = $this->salesOrder->discountForDelivery($invoiceTotal, $isFinalDelivery);
            $netTotal = round($invoiceTotal - $discount, 2);

            $invoice = SalesInvoice::create([
                'number' => $numbers->generate('sales_invoice'),
                'sales_order_id' => $this->salesOrder->id,
                'retailer_id' => $this->salesOrder->retailer_id,
                'invoice_date' => today(),
                'subtotal' => $invoiceTotal,
                'discount' => $discount,
                'grand_total' => $netTotal,
                'paid_amount' => 0,
                'due_amount' => $netTotal,
                'status' => 'unpaid',
            ]);

            foreach ($invoiceLineData as $line) {
                SalesInvoiceItem::create(['sales_invoice_id' => $invoice->id, ...$line]);
            }

            $retailer = Retailer::whereKey($this->salesOrder->retailer_id)->lockForUpdate()->first();
            $retailer->increment('current_due', $netTotal);

            $retailer->ledgerEntries()->create([
                'type' => 'invoice',
                'reference_type' => $invoice->getMorphClass(),
                'reference_id' => $invoice->getKey(),
                'debit' => $netTotal,
                'credit' => 0,
                'balance_after' => $retailer->current_due,
                'entry_date' => today(),
            ]);

            $deliveryInvoices->createDeliveryInvoice($deliveryNote, $discount);
            $smsInvoice = ['number' => $invoice->number, 'amount' => $netTotal, 'due' => (float) $retailer->current_due, 'phone' => $retailer->phone];

            $this->salesOrder->refresh();
            $allDelivered = $this->salesOrder->items()->whereColumn('delivered_quantity', '<', 'quantity')->doesntExist();
            $anyDelivered = $this->salesOrder->items()->where('delivered_quantity', '>', 0)->exists();

            $this->salesOrder->update([
                'status' => $allDelivered ? 'completed' : ($anyDelivered ? 'partial' : $this->salesOrder->status),
            ]);
        });

        if ($smsInvoice) {
            app(\App\Services\SmsService::class)->send(
                $smsInvoice['phone'],
                \App\Services\SmsService::brand("Delivered against order {$this->salesOrder->number}. Invoice {$smsInvoice['number']}: Tk ".number_format($smsInvoice['amount'], 2).'. Total due now Tk '.number_format($smsInvoice['due'], 2).'.'),
                'delivered',
            );
        }

        $this->redirect(route('sales.orders.show', $this->salesOrder), navigate: false);
    }

    public function render()
    {
        return view('livewire.delivery.create');
    }
}
