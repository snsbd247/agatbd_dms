<?php

namespace App\Livewire\Warranty\Claims;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Models\User;
use App\Models\Warranty\WarrantyClaim;
use App\Models\Warranty\WarrantyPolicy;
use App\Notifications\WarrantyClaimPending;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Warranty Claim')]
class Create extends Component
{
    public ?int $retailer_id = null;
    public ?int $product_id = null;
    public ?int $sales_invoice_id = null;
    public string $claim_date;
    public string $issue_description = '';

    /** Set when editing a claim that is still pending. */
    public ?int $editingId = null;

    public function mount(?WarrantyClaim $warrantyClaim = null): void
    {
        $this->authorize('warranty.manage');
        $this->claim_date = today()->toDateString();

        if ($warrantyClaim?->exists) {
            abort_unless($warrantyClaim->status === 'pending', 403, __('Only a pending warranty claim can be changed.'));
            $this->editingId = $warrantyClaim->id;
            $this->retailer_id = $warrantyClaim->retailer_id;
            $this->product_id = $warrantyClaim->product_id;
            $this->sales_invoice_id = $warrantyClaim->sales_invoice_id;
            $this->claim_date = $warrantyClaim->claim_date->toDateString();
            $this->issue_description = (string) $warrantyClaim->issue_description;
        }
    }

    public function save(NumberGeneratorService $numbers): void
    {
        $this->authorize('warranty.manage');

        $data = $this->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'product_id' => 'required|exists:products,id',
            'sales_invoice_id' => 'nullable|exists:sales_invoices,id',
            'claim_date' => 'required|date',
            'issue_description' => 'nullable|string|max:1000',
        ]);

        if ($this->editingId) {
            $claim = WarrantyClaim::findOrFail($this->editingId);
            if ($claim->status !== 'pending') {
                $this->addError('issue_description', __('This claim can no longer be changed.'));

                return;
            }
            $claim->update([
                'retailer_id' => $data['retailer_id'],
                'product_id' => $data['product_id'],
                'sales_invoice_id' => $data['sales_invoice_id'] ?: null,
                'claim_date' => $data['claim_date'],
                'issue_description' => $data['issue_description'] ?: null,
            ]);
            $this->redirect(route('warranty.show', $claim), navigate: false);

            return;
        }

        $claim = WarrantyClaim::create([
            'number' => $numbers->generate('warranty_claim'),
            'retailer_id' => $data['retailer_id'],
            'product_id' => $data['product_id'],
            'sales_invoice_id' => $data['sales_invoice_id'] ?: null,
            'claim_date' => $data['claim_date'],
            'issue_description' => $data['issue_description'] ?: null,
            'status' => 'pending',
        ]);

        $recipients = \App\Support\Staff::approvers(\App\Support\Staff::dealerOfRetailer($claim->retailer_id), 'warranty_claim_pending');
        Notification::send($recipients, new WarrantyClaimPending($claim));

        $this->redirect(route('warranty.show', $claim), navigate: false);
    }

    public function render()
    {
        $user = Auth::user();
        $representative = $user->representative;

        $retailers = $representative
            ? Retailer::where('representative_id', $representative->id)->orderBy('name')->get()
            : Retailer::orderBy('name')->get();

        $invoices = $this->retailer_id
            ? SalesInvoice::where('retailer_id', $this->retailer_id)->orderByDesc('invoice_date')->get()
            : collect();

        $policy = $this->product_id
            ? (WarrantyPolicy::where('product_id', $this->product_id)->first()
                ?? WarrantyPolicy::where('category_id', Product::find($this->product_id)?->category_id)->first())
            : null;

        return view('livewire.warranty.claims.create', [
            'retailers' => $retailers,
            'products' => Product::orderBy('name')->get(),
            'invoices' => $invoices,
            'policy' => $policy,
        ])->title($this->editingId ? 'Edit Warranty Claim' : 'New Warranty Claim');
    }
}
