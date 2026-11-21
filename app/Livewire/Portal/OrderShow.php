<?php

namespace App\Livewire\Portal;

use App\Models\Sales\SalesOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Order')]
class OrderShow extends Component
{
    use ForCurrentRetailer;

    public SalesOrder $salesOrder;

    public function mount(): void
    {
        abort_unless($this->salesOrder->retailer_id === $this->retailer()->id, 404);
    }

    public function cancel(): void
    {
        abort_unless($this->salesOrder->retailer_id === $this->retailer()->id, 404);

        if (! in_array($this->salesOrder->status, ['pending', 'credit_hold'], true)) {
            $this->addError('cancel', 'This order is already approved and can no longer be cancelled here. Please contact the dealer.');

            return;
        }

        $this->salesOrder->update(['status' => 'cancelled']);
    }

    public function render()
    {
        $this->salesOrder->load(['items.product.unit', 'deliveryNotes', 'deliveryInvoices', 'invoices']);

        return view('livewire.portal.order-show', ['order' => $this->salesOrder]);
    }
}
