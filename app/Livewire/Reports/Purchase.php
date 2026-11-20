<?php

namespace App\Livewire\Reports;

use App\Models\Company;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Purchase Report')]
class Purchase extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $company_id = '';

    #[Url]
    public string $status = '';

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
        return PurchaseOrder::query()
            ->whereBetween('order_date', [Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay()])
            ->when($this->company_id, fn ($q) => $q->where('company_id', $this->company_id))
            ->when($this->status, fn ($q) => $q->where('status', $this->status));
    }

    public function render()
    {
        $orders = $this->baseQuery()
            ->with(['company', 'warehouse'])
            ->latest('order_date')
            ->latest('id')
            ->paginate(15);

        $summaryQuery = $this->baseQuery();
        $totalValue = (float) (clone $summaryQuery)->sum('total_amount');
        $orderCount = (clone $summaryQuery)->count();

        $summary = [
            'total_value' => $totalValue,
            'order_count' => $orderCount,
            'avg_order' => $orderCount > 0 ? $totalValue / $orderCount : 0,
        ];

        return view('livewire.reports.purchase', [
            'orders' => $orders,
            'summary' => $summary,
            'companies' => Company::orderBy('name')->get(),
        ]);
    }
}
