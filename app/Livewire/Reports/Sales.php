<?php

namespace App\Livewire\Reports;

use App\Models\Representative;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Sales Report')]
class Sales extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $retailer_id = '';

    #[Url]
    public string $representative_id = '';

    public function mount(): void
    {
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    private function baseQuery()
    {
        return SalesInvoice::query()
            ->whereBetween('invoice_date', [Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay()])
            ->when($this->retailer_id, fn ($q) => $q->where('retailer_id', $this->retailer_id))
            ->when($this->representative_id, fn ($q) => $q->whereHas('salesOrder', fn ($sq) => $sq->where('representative_id', $this->representative_id)));
    }

    public function render()
    {
        $invoices = $this->baseQuery()
            ->with(['retailer', 'salesOrder.representative'])
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(15);

        $summaryQuery = $this->baseQuery();
        $totalSales = (float) (clone $summaryQuery)->sum('grand_total');
        $invoiceCount = (clone $summaryQuery)->count();

        $summary = [
            'total_sales' => $totalSales,
            'invoice_count' => $invoiceCount,
            'avg_invoice' => $invoiceCount > 0 ? $totalSales / $invoiceCount : 0,
        ];

        return view('livewire.reports.sales', [
            'invoices' => $invoices,
            'summary' => $summary,
            'retailers' => Retailer::orderBy('name')->get(),
            'representatives' => Representative::orderBy('name')->get(),
        ]);
    }
}
