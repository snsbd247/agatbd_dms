<?php

namespace App\Livewire\Reports;

use App\Models\Area;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Retailer Report')]
class Retailers extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $area_id = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $retailers = Retailer::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->area_id, fn ($q) => $q->where('area_id', $this->area_id))
            ->withCount('salesOrders')
            ->withSum('invoices', 'grand_total')
            ->withSum('collections', 'amount')
            ->orderByDesc('current_due')
            ->paginate(15);

        $summary = [
            'total_retailers' => Retailer::count(),
            'total_due' => (float) Retailer::sum('current_due'),
            'total_invoiced' => (float) SalesInvoice::sum('grand_total'),
        ];

        return view('livewire.reports.retailers', [
            'retailers' => $retailers,
            'summary' => $summary,
            'areas' => Area::orderBy('name')->get(),
        ]);
    }
}
