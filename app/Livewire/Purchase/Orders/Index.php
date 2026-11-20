<?php

namespace App\Livewire\Purchase\Orders;

use App\Models\Purchase\PurchaseOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Purchase Orders')]
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
        $orders = PurchaseOrder::query()
            ->with(['company', 'warehouse'])
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
            ->latest('order_date')
            ->paginate(10);

        $summary = [
            'total' => PurchaseOrder::count(),
            'draft' => PurchaseOrder::where('status', 'draft')->count(),
            'in_progress' => PurchaseOrder::whereIn('status', ['sent', 'partial'])->count(),
            'completed' => PurchaseOrder::where('status', 'completed')->count(),
        ];

        return view('livewire.purchase.orders.index', compact('orders', 'summary'));
    }
}
