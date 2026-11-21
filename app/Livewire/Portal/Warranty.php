<?php

namespace App\Livewire\Portal;

use App\Models\Product;
use App\Models\Sales\SalesInvoice;
use App\Models\User;
use App\Models\Warranty\WarrantyClaim;
use App\Models\Warranty\WarrantyPolicy;
use App\Notifications\WarrantyClaimPending;
use App\Services\NumberGeneratorService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A retailer files a warranty claim for a product they bought. The product
 * and invoice lists only ever contain this retailer's own purchases.
 */
#[Layout('layouts.app')]
#[Title('My Warranty Claims')]
class Warranty extends Component
{
    use ForCurrentRetailer, WithPagination;

    public bool $showForm = false;
    public ?int $product_id = null;
    public ?int $sales_invoice_id = null;
    public string $issue_description = '';

    public function mount(): void
    {
        $this->retailer();
    }

    public function openForm(): void
    {
        $this->reset('product_id', 'sales_invoice_id', 'issue_description');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function updatedProductId(): void
    {
        $this->sales_invoice_id = null;
    }

    private function boughtProducts()
    {
        $retailerId = $this->retailer()->id;

        return Product::whereHas('salesInvoiceItems.salesInvoice', fn ($q) => $q->where('retailer_id', $retailerId))
            ->orderBy('name')
            ->get();
    }

    private function invoicesFor(?int $productId)
    {
        if (! $productId) {
            return collect();
        }

        return SalesInvoice::where('retailer_id', $this->retailer()->id)
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->orderByDesc('invoice_date')
            ->get();
    }

    private function policyFor(?int $productId): ?WarrantyPolicy
    {
        if (! $productId) {
            return null;
        }

        return WarrantyPolicy::where('product_id', $productId)->first()
            ?? WarrantyPolicy::whereNull('product_id')->where('category_id', Product::find($productId)?->category_id)->first();
    }

    public function save(NumberGeneratorService $numbers): void
    {
        $retailer = $this->retailer();

        $this->validate([
            'product_id' => 'required|integer',
            'sales_invoice_id' => 'nullable|integer',
            'issue_description' => 'required|string|min:5|max:1000',
        ]);

        if (! $this->boughtProducts()->contains('id', $this->product_id)) {
            $this->addError('product_id', __('You have not bought this product.'));

            return;
        }

        if ($this->sales_invoice_id && ! $this->invoicesFor($this->product_id)->contains('id', $this->sales_invoice_id)) {
            $this->addError('sales_invoice_id', __('This invoice does not contain the selected product.'));

            return;
        }

        $claim = WarrantyClaim::create([
            'number' => $numbers->generate('warranty_claim'),
            'retailer_id' => $retailer->id,
            'product_id' => $this->product_id,
            'sales_invoice_id' => $this->sales_invoice_id ?: null,
            'claim_date' => today(),
            'issue_description' => $this->issue_description,
            'status' => 'pending',
        ]);

        $recipients = \App\Support\Staff::approvers(\App\Support\Staff::dealerOfRetailer($claim->retailer_id), 'warranty_claim_pending');
        \Illuminate\Support\Facades\Notification::send($recipients, new WarrantyClaimPending($claim));

        $this->showForm = false;
        session()->flash('status', __('Warranty claim :number was sent to the dealer.', ['number' => $claim->number]));
    }

    public function render()
    {
        $claims = WarrantyClaim::query()
            ->where('retailer_id', $this->retailer()->id)
            ->with(['product', 'salesInvoice'])
            ->latest('claim_date')
            ->latest('id')
            ->paginate(10);

        $invoices = $this->showForm ? $this->invoicesFor($this->product_id) : collect();
        $policy = $this->showForm ? $this->policyFor($this->product_id) : null;
        $expired = null;

        if ($policy && $invoices->isNotEmpty()) {
            $invoice = $this->sales_invoice_id ? $invoices->firstWhere('id', $this->sales_invoice_id) : $invoices->first();
            $expired = $invoice && $invoice->invoice_date->copy()->addMonths($policy->duration_months)->isPast();
        }

        return view('livewire.portal.warranty', [
            'claims' => $claims,
            'products' => $this->showForm ? $this->boughtProducts() : collect(),
            'invoices' => $invoices,
            'policy' => $policy,
            'expired' => $expired,
        ]);
    }
}
