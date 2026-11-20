<?php

namespace App\Livewire\Delivery\Invoices;

use App\Models\Delivery\DeliveryNote;
use App\Services\DeliveryInvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Invoices are created automatically with every new delivery. This page is
 * for delivery notes made before that (or if creation failed): it lets the
 * user generate the missing invoice by hand.
 */
#[Layout('layouts.app')]
#[Title('Create Delivery Invoice')]
class Create extends Component
{
    public DeliveryNote $deliveryNote;

    public function mount(): void
    {
        $existing = $this->deliveryNote->invoices()->first();

        if ($existing) {
            $this->redirect(route('delivery.invoices.show', $existing));
        }
    }

    public function createInvoice(DeliveryInvoiceService $service): void
    {
        $this->authorize('delivery.manage');

        try {
            $invoice = $service->createDeliveryInvoice($this->deliveryNote);
        } catch (\RuntimeException $e) {
            $this->addError('invoice', $e->getMessage());

            return;
        }

        $this->redirect(route('delivery.invoices.show', $invoice));
    }

    public function render()
    {
        $this->deliveryNote->load(['items.product', 'items.salesOrderItem', 'items.batch', 'salesOrder.retailer']);

        return view('livewire.delivery.invoices.create');
    }
}
