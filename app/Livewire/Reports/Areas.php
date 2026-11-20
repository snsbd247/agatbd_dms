<?php

namespace App\Livewire\Reports;

use App\Models\Area;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Area Report')]
class Areas extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $start = Carbon::parse($this->from)->startOfDay();
        $end = Carbon::parse($this->to)->endOfDay();

        $areas = Area::query()
            ->withCount(['retailers', 'representatives'])
            ->orderBy('name')
            ->paginate(15);

        $rows = $areas->getCollection()->map(function (Area $area) use ($start, $end) {
            $sales = (float) SalesInvoice::whereHas('retailer', fn ($q) => $q->where('area_id', $area->id))
                ->whereBetween('invoice_date', [$start, $end])
                ->sum('grand_total');
            $due = (float) Retailer::where('area_id', $area->id)->sum('current_due');

            return [
                'area' => $area,
                'sales' => $sales,
                'due' => $due,
            ];
        });
        $areas->setCollection($rows);

        $summary = [
            'total_areas' => Area::count(),
            'total_sales' => (float) SalesInvoice::whereBetween('invoice_date', [$start, $end])->sum('grand_total'),
            'total_due' => (float) Retailer::sum('current_due'),
        ];

        return view('livewire.reports.areas', [
            'rows' => $areas,
            'summary' => $summary,
        ]);
    }
}
