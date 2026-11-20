<?php

namespace App\Livewire\Delivery\Invoices;

use App\Models\Delivery\DeliveryInvoice;
use App\Services\DeliveryInvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Delivery Invoice')]
class Show extends Component
{
    public DeliveryInvoice $deliveryInvoice;

    public function finalizeInvoice(DeliveryInvoiceService $service): void
    {
        $this->authorize('delivery.manage');
        $service->finalizeInvoice($this->deliveryInvoice);
        $this->deliveryInvoice->refresh();
    }

    public function render()
    {
        $this->deliveryInvoice->load(['items.product.unit', 'items.batch', 'salesOrder', 'retailer', 'deliveryNote']);

        return view('livewire.delivery.invoices.show', ['invoice' => $this->deliveryInvoice]);
    }
}
