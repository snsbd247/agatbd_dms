<?php

namespace App\Livewire\Warranty\Claims;

use App\Models\Warranty\WarrantyClaim;
use App\Models\Warranty\WarrantyPolicy;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Warranty Claim')]
class Show extends Component
{
    public WarrantyClaim $warrantyClaim;

    public string $note = '';

    private function changeStatus(string $status): void
    {
        $this->authorize('warranty.manage');

        $this->warrantyClaim->update(['status' => $status]);

        $this->warrantyClaim->tracking()->create([
            'status' => $status,
            'note' => $this->note ?: null,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);

        $this->note = '';
    }

    public function approve(): void
    {
        $this->changeStatus('approved');
    }

    public function reject(): void
    {
        $this->changeStatus('rejected');
    }

    public function markReplaced(): void
    {
        $this->changeStatus('replaced');
    }

    public function markRepaired(): void
    {
        $this->changeStatus('repaired');
    }

    public function render()
    {
        $this->warrantyClaim->load(['retailer', 'product', 'salesInvoice', 'tracking' => fn ($q) => $q->orderByDesc('changed_at')]);

        $policy = WarrantyPolicy::where('product_id', $this->warrantyClaim->product_id)->first()
            ?? WarrantyPolicy::where('category_id', $this->warrantyClaim->product?->category_id)->first();

        $expiry = null;
        if ($policy && $this->warrantyClaim->salesInvoice) {
            $expiry = Carbon::parse($this->warrantyClaim->salesInvoice->invoice_date)->addMonths($policy->duration_months);
        }

        return view('livewire.warranty.claims.show', [
            'policy' => $policy,
            'expiry' => $expiry,
        ]);
    }
}
