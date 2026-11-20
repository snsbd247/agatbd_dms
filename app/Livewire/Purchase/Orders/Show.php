<?php

namespace App\Livewire\Purchase\Orders;

use App\Models\Purchase\PurchaseOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Purchase Order')]
class Show extends Component
{
    public PurchaseOrder $purchaseOrder;

    public function send(): void
    {
        $this->authorize('purchase.manage');

        if ($this->purchaseOrder->status !== 'draft') {
            return;
        }

        $this->purchaseOrder->update(['status' => 'sent']);
    }

    public function cancel(): void
    {
        $this->authorize('purchase.manage');

        $hasReceipts = $this->purchaseOrder->items()->where('received_quantity', '>', 0)->exists();

        if (! in_array($this->purchaseOrder->status, ['draft', 'sent']) || $hasReceipts) {
            return;
        }

        $this->purchaseOrder->update(['status' => 'cancelled']);
    }

    public function render()
    {
        $this->purchaseOrder->load(['company', 'warehouse', 'items.product', 'goodsReceipts']);

        return view('livewire.purchase.orders.show');
    }
}
