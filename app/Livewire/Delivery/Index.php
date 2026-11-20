<?php

namespace App\Livewire\Delivery;

use App\Models\Delivery\DeliveryNote;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Deliveries')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter_type = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_type']);
        $this->resetPage();
    }

    public function setTypeFilter(string $type): void
    {
        $this->filter_type = $type;
        $this->resetPage();
    }

    public function render()
    {
        $notes = DeliveryNote::query()
            ->with(['salesOrder.retailer'])
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->filter_type, fn ($q) => $q->where('delivery_type', $this->filter_type))
            ->latest('delivery_date')
            ->paginate(10);

        $summary = [
            'total' => DeliveryNote::count(),
            'today' => DeliveryNote::where('delivery_date', today())->count(),
            'this_month' => DeliveryNote::where('delivery_date', '>=', today()->startOfMonth())->count(),
            'representative' => DeliveryNote::where('delivery_type', 'representative')->count(),
        ];

        return view('livewire.delivery.index', compact('notes', 'summary'));
    }
}
