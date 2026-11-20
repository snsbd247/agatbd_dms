<?php

namespace App\Livewire\Sales\Orders;

use App\Models\Sales\SalesOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sales Order')]
class Show extends Component
{
    public SalesOrder $salesOrder;

    public function approve(): void
    {
        $this->authorize('sales.approve');

        if (! in_array($this->salesOrder->status, ['pending', 'credit_hold'])) {
            return;
        }

        $this->salesOrder->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    public function reject(): void
    {
        $this->authorize('sales.approve');

        if (! in_array($this->salesOrder->status, ['pending', 'credit_hold'])) {
            return;
        }

        $this->salesOrder->update(['status' => 'cancelled']);
    }

    public function printDeliveryInvoices()
    {
        return redirect()->route('delivery.sales-order.invoices.print', $this->salesOrder->id);
    }

    public function render()
    {
        $this->salesOrder->load(['retailer', 'representative', 'warehouse', 'items.product', 'deliveryNotes', 'invoices', 'deliveryInvoices']);

        return view('livewire.sales.orders.show');
    }
}

