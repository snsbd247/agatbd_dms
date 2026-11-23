<?php

namespace App\Livewire\Purchase\Receipts;

use App\Models\Purchase\GoodsReceipt;
use App\Models\Purchase\GoodsReceiptItem;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderItem;
use App\Services\InventoryService;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Receive Goods')]
class Create extends Component
{
    public PurchaseOrder $purchaseOrder;

    public array $lines = [];

    /** The supplier's VAT invoice (Mushak 6.3): its number and the VAT on it. */
    public string $supplier_invoice_no = '';
    public string $vat_amount = '';

    public function mount(): void
    {
        $this->authorize('purchase.manage');

        abort_unless(in_array($this->purchaseOrder->status, ['sent', 'partial']), 403);

        $this->purchaseOrder->load('items.product');

        $this->lines = $this->purchaseOrder->items
            ->map(function (PurchaseOrderItem $item) {
                $remaining = $item->quantity - $item->received_quantity;

                return [
                    'item_id' => $item->id,
                    'product_name' => $item->product->name,
                    'remaining' => $remaining,
                    'quantity' => $remaining > 0 ? $remaining : 0,
                    'batch_no' => '',
                    'expiry_date' => '',
                    'unit_cost' => (string) $item->unit_price,
                ];
            })
            ->filter(fn ($line) => $line['remaining'] > 0)
            ->values()
            ->toArray();
    }

    public function save(NumberGeneratorService $numbers, InventoryService $inventory): void
    {
        $this->authorize('purchase.manage');
        // First: validate() clears the error bag, so it must run before the line checks below.
        $this->validate([
            'supplier_invoice_no' => 'nullable|string|max:60',
            'vat_amount' => 'nullable|numeric|min:0',
        ]);

        $hasAny = false;

        foreach ($this->lines as $index => $line) {
            $qty = (float) ($line['quantity'] ?: 0);

            if ($qty < 0) {
                $this->addError("lines.$index.quantity", 'Quantity cannot be negative.');
            } elseif ($qty > (float) $line['remaining']) {
                $this->addError("lines.$index.quantity", 'Cannot receive more than the remaining ordered quantity.');
            } elseif ($qty > 0) {
                $hasAny = true;

                if (blank($line['batch_no'] ?? null)) {
                    $this->addError("lines.$index.batch_no", 'Batch number is required.');
                }

                if (! is_numeric($line['unit_cost'] ?? null) || (float) $line['unit_cost'] < 0) {
                    $this->addError("lines.$index.unit_cost", 'Unit cost must be a non-negative number.');
                }
            }
        }

        if (! $hasAny) {
            $this->addError('lines', 'Enter a quantity for at least one item.');
        }


        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($numbers, $inventory) {
            $receipt = GoodsReceipt::create([
                'number' => $numbers->generate('goods_receipt'),
                'purchase_order_id' => $this->purchaseOrder->id,
                'warehouse_id' => $this->purchaseOrder->warehouse_id,
                'receipt_date' => today(),
                'supplier_invoice_no' => $this->supplier_invoice_no ?: null,
                'vat_amount' => \App\Services\Vat\VatService::enabled() ? round((float) $this->vat_amount, 2) : 0,
                'total_amount' => 0,
                'created_by' => Auth::id(),
            ]);

            $total = 0;

            foreach ($this->lines as $line) {
                $qty = (float) $line['quantity'];

                if ($qty <= 0) {
                    continue;
                }

                $item = PurchaseOrderItem::findOrFail($line['item_id']);

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $item->product_id,
                    'batch_no' => $line['batch_no'],
                    'expiry_date' => $line['expiry_date'] ?: null,
                    'quantity' => $qty,
                    'unit_cost' => $line['unit_cost'],
                ]);

                $inventory->receiveStock(
                    productId: $item->product_id,
                    warehouseId: $this->purchaseOrder->warehouse_id,
                    batchNo: $line['batch_no'],
                    expiryDate: $line['expiry_date'] ?: null,
                    costPrice: (float) $line['unit_cost'],
                    quantity: $qty,
                    reference: $receipt,
                );

                $item->increment('received_quantity', $qty);
                $total += $qty * $line['unit_cost'];
            }

            $receipt->update(['total_amount' => $total]);
            app(\App\Services\Accounts\AutoPosting::class)->goodsReceipt($receipt);

            $this->purchaseOrder->refresh();
            $allReceived = $this->purchaseOrder->items()->whereColumn('received_quantity', '<', 'quantity')->doesntExist();
            $anyReceived = $this->purchaseOrder->items()->where('received_quantity', '>', 0)->exists();

            $this->purchaseOrder->update([
                'status' => $allReceived ? 'completed' : ($anyReceived ? 'partial' : $this->purchaseOrder->status),
            ]);
        });

        $this->redirect(route('purchase.orders.show', $this->purchaseOrder), navigate: false);
    }

    public function render()
    {
        return view('livewire.purchase.receipts.create', ['vatOn' => \App\Services\Vat\VatService::enabled()]);
    }
}
