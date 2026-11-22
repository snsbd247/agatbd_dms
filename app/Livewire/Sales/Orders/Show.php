<?php

namespace App\Livewire\Sales\Orders;

use App\Models\Sales\SalesOrder;
use App\Services\SmsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sales Order')]
class Show extends Component
{
    public SalesOrder $salesOrder;

    public string $discountType = 'amount';
    public string $discountValue = '';

    /**
     * Discount can be set by an approver while the order is not yet approved.
     * It is then shared across the deliveries (see SalesOrder::discountForDelivery).
     */
    public function applyDiscount(): void
    {
        $this->authorize('sales.approve');

        if (! in_array($this->salesOrder->status, ['pending', 'credit_hold'], true)) {
            $this->addError('discountValue', 'Discount can only be changed before the order is approved.');

            return;
        }

        $subtotal = (float) $this->salesOrder->subtotal;

        $this->validate([
            'discountType' => 'required|in:amount,percent',
            'discountValue' => ['required', 'numeric', 'min:0', $this->discountType === 'percent' ? 'max:100' : 'max:'.$subtotal],
        ], [
            'discountValue.max' => $this->discountType === 'percent' ? 'Percent cannot be more than 100.' : 'Discount cannot be more than the order subtotal.',
        ]);

        $discount = $this->discountType === 'percent'
            ? round($subtotal * (float) $this->discountValue / 100, 2)
            : round((float) $this->discountValue, 2);

        $this->salesOrder->update([
            'discount' => $discount,
            'grand_total' => round($subtotal - $discount, 2),
        ]);

        $this->reset('discountValue');
    }

    public function removeDiscount(): void
    {
        $this->discountType = 'amount';
        $this->discountValue = '0';
        $this->applyDiscount();
    }

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

        app(SmsService::class)->send(
            $this->salesOrder->retailer?->phone,
            SmsService::message('order_approved', [
                'retailer' => $this->salesOrder->retailer?->name,
                'order' => $this->salesOrder->number,
                'amount' => $this->salesOrder->grand_total,
            ]),
            'order_approved',
        );
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

        return view('livewire.sales.orders.show', [
            'vatOn' => \App\Services\Vat\VatService::appliesTo($this->salesOrder->retailer),
        ]);
    }
}

