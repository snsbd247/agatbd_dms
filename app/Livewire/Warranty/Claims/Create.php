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

    public function mount(): void
    {
        $this->authorize('warranty.manage');
        $this->claim_date = today()->toDateString();
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

        $claim = WarrantyClaim::create([
            'number' => $numbers->generate('warranty_claim'),
            'retailer_id' => $data['retailer_id'],
            'product_id' => $data['product_id'],
            'sales_invoice_id' => $data['sales_invoice_id'] ?: null,
            'claim_date' => $data['claim_date'],
            'issue_description' => $data['issue_description'] ?: null,
            'status' => 'pending',
        ]);

        $recipients = User::role(['Dealer', 'Manager'])->get()->filter(fn (User $u) => $u->wantsNotification('warranty_claim_pending'));
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
        ]);
    }
}
