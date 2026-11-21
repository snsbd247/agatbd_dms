<?php

namespace App\Livewire\Portal;

use App\Models\Sales\SalesOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('My Orders')]
class Orders extends Component
{
    use ForCurrentRetailer, WithPagination;

    public string $status = '';

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function render()
    {
        $retailer = $this->retailer();

        $orders = SalesOrder::query()
            ->where('retailer_id', $retailer->id)
            ->withCount('items')
            ->when($this->status === 'open', fn ($q) => $q->whereIn('status', ['pending', 'credit_hold', 'approved', 'partial']))
            ->when($this->status === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->when($this->status === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->latest('order_date')
            ->latest('id')
            ->paginate(10);

        return view('livewire.portal.orders', compact('orders', 'retailer'));
    }
}
