<?php

namespace App\Livewire\Purchase\Receipts;

use App\Models\Purchase\GoodsReceipt;
use App\Models\Warehouse;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Goods Receipts')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter_warehouse_id = '';
    public string $filter_status = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_warehouse_id']);
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->filter_status = $status;
        $this->resetPage();
    }

    public function render()
    {
        $receipts = GoodsReceipt::query()
            ->with(['warehouse', 'purchaseOrder'])
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->filter_warehouse_id, fn ($q) => $q->where('warehouse_id', $this->filter_warehouse_id))
            ->when($this->filter_status, fn ($q) => $q->whereHas('purchaseOrder', fn ($poq) => $poq->where('status', $this->filter_status)))
            ->latest('receipt_date')
            ->paginate(10);

        $summary = [
            'total' => GoodsReceipt::count(),
            'total_value' => GoodsReceipt::sum('total_amount'),
            'this_month' => GoodsReceipt::where('receipt_date', '>=', today()->startOfMonth())->count(),
            'this_month_value' => GoodsReceipt::where('receipt_date', '>=', today()->startOfMonth())->sum('total_amount'),
        ];

        return view('livewire.purchase.receipts.index', [
            'receipts' => $receipts,
            'summary' => $summary,
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }
}
