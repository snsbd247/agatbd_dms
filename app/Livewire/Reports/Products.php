<?php

namespace App\Livewire\Reports;

use App\Models\Sales\SalesInvoiceItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Product Performance Report')]
class Products extends Component
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

    private function groupedRows()
    {
        $start = Carbon::parse($this->from)->startOfDay();
        $end = Carbon::parse($this->to)->endOfDay();

        $items = SalesInvoiceItem::query()
            ->whereHas('salesInvoice', fn ($q) => $q->whereBetween('invoice_date', [$start, $end]))
            ->with(['product', 'batch'])
            ->get();

        return $items->groupBy('product_id')->map(function ($group) {
            $profit = $group->sum(function (SalesInvoiceItem $item) {
                $cost = $item->batch?->cost_price ?? $item->product?->purchase_price ?? 0;

                return (float) $item->line_total - ((float) $cost * (float) $item->quantity);
            });

            return [
                'product' => $group->first()->product,
                'quantity' => (float) $group->sum('quantity'),
                'revenue' => (float) $group->sum('line_total'),
                'profit' => $profit,
            ];
        })->filter(fn ($row) => $row['product'] !== null)->sortByDesc('revenue')->values();
    }

    public function render()
    {
        $allRows = $this->groupedRows();
        $perPage = 15;
        $page = $this->getPage();

        $rows = new LengthAwarePaginator(
            $allRows->forPage($page, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        $summary = [
            'total_revenue' => (float) $allRows->sum('revenue'),
            'total_profit' => (float) $allRows->sum('profit'),
            'product_count' => $allRows->count(),
        ];

        return view('livewire.reports.products', [
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }
}
