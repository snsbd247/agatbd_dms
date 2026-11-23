<?php

namespace App\Livewire\Sales\Orders;

use App\Models\Sales\SalesOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Sales Orders')]
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

    /** Delete an order that is not approved yet and has nothing delivered or invoiced. */
    public function delete(int $id): void
    {
        $this->authorize('sales.create');
        $order = SalesOrder::findOrFail($id);
        $rep = auth()->user()->representative;
        abort_if($rep && $order->representative_id !== $rep->id, 403);

        if (! $order->deleteIfEditable()) {
            session()->flash('error', __('Only a pending order with no delivery or invoice can be deleted. Cancel it instead.'));
        }
    }

    public function render()
    {
        $orders = SalesOrder::query()
            ->with(['retailer'])
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
            ->latest('order_date')
            ->paginate(10);

        $summary = [
            'total' => SalesOrder::count(),
            'pending' => SalesOrder::where('status', 'pending')->count(),
            'credit_hold' => SalesOrder::where('status', 'credit_hold')->count(),
            'completed' => SalesOrder::where('status', 'completed')->count(),
        ];

        return view('livewire.sales.orders.index', compact('orders', 'summary'));
    }
}
