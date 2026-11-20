<?php

namespace App\Livewire\Delivery\Invoices;

use App\Models\Delivery\DeliveryInvoice;
use App\Models\Delivery\DeliveryNote;
use App\Services\DeliveryInvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Delivery Invoices')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function finalizeInvoice(int $invoiceId, DeliveryInvoiceService $service): void
    {
        $this->authorize('delivery.manage');
        $service->finalizeInvoice(DeliveryInvoice::findOrFail($invoiceId));
    }

    public function deleteInvoice(int $invoiceId): void
    {
        $this->authorize('delivery.manage');

        $invoice = DeliveryInvoice::findOrFail($invoiceId);

        if ($invoice->status !== 'pending') {
            $this->addError('invoices', 'Only pending invoices can be deleted.');

            return;
        }

        $invoice->items()->delete();
        $invoice->delete();
    }

    public function render()
    {
        $invoices = DeliveryInvoice::query()
            ->with(['salesOrder', 'retailer', 'deliveryNote'])
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('number', 'like', "%{$this->search}%")
                        ->orWhereHas('retailer', fn ($r) => $r->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(10);

        $summary = [
            'total' => DeliveryInvoice::count(),
            'pending' => DeliveryInvoice::where('status', 'pending')->count(),
            'finalized' => DeliveryInvoice::where('status', 'finalized')->count(),
            'printed' => DeliveryInvoice::where('status', 'printed')->count(),
        ];

        $uninvoicedNotes = DeliveryNote::query()
            ->whereDoesntHave('invoices')
            ->has('items')
            ->with('salesOrder.retailer')
            ->latest('delivery_date')
            ->limit(10)
            ->get();

        return view('livewire.delivery.invoices.index', compact('invoices', 'summary', 'uninvoicedNotes'));
    }
}
