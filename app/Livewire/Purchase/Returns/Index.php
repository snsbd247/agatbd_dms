<?php

namespace App\Livewire\Purchase\Returns;

use App\Models\Purchase\PurchaseReturn;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Purchase Returns')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter_status = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_status']);
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->filter_status = $status;
        $this->resetPage();
    }

    public function render()
    {
        $purchaseReturns = PurchaseReturn::query()
            ->with(['company', 'warehouse'])
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
            ->latest('return_date')
            ->paginate(10);

        $summary = [
            'total' => PurchaseReturn::count(),
            'draft' => PurchaseReturn::where('status', 'draft')->count(),
            'sent' => PurchaseReturn::where('status', 'sent')->count(),
            'completed' => PurchaseReturn::where('status', 'completed')->count(),
        ];

        return view('livewire.purchase.returns.index', compact('purchaseReturns', 'summary'));
    }
}
