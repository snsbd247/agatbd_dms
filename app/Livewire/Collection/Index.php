<?php

namespace App\Livewire\Collection;

use App\Models\PaymentCollection;
use App\Models\Sales\SalesInvoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Collections')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter_method = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_method']);
        $this->resetPage();
    }

    public function setMethodFilter(string $method): void
    {
        $this->filter_method = $method;
        $this->resetPage();
    }

    public function render()
    {
        $collections = PaymentCollection::query()
            ->with('retailer')
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->filter_method, fn ($q) => $q->where('method', $this->filter_method))
            ->latest('collection_date')
            ->paginate(10);

        $monthSales = (float) SalesInvoice::where('invoice_date', '>=', today()->startOfMonth())->sum('grand_total');
        $monthCollection = (float) PaymentCollection::where('collection_date', '>=', today()->startOfMonth())->sum('amount');

        $summary = [
            'total' => PaymentCollection::count(),
            'total_amount' => PaymentCollection::sum('amount'),
            'today' => PaymentCollection::where('collection_date', today())->sum('amount'),
            'this_month' => $monthCollection,
            'collection_rate' => $monthSales > 0 ? round(($monthCollection / $monthSales) * 100, 1) : 0,
        ];

        return view('livewire.collection.index', compact('collections', 'summary'));
    }
}
