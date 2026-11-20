<?php

namespace App\Livewire\Reports;

use App\Models\Company;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseReturn;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Company Report')]
class Companies extends Component
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

        $companies = Company::query()->orderBy('name')->paginate(15);

        $rows = $companies->getCollection()->map(function (Company $company) use ($start, $end) {
            return [
                'company' => $company,
                'order_count' => $company->purchaseOrders()->whereBetween('order_date', [$start, $end])->count(),
                'total_value' => (float) $company->purchaseOrders()->whereBetween('order_date', [$start, $end])->sum('total_amount'),
                'return_count' => $company->purchaseReturns()->whereBetween('return_date', [$start, $end])->count(),
            ];
        });
        $companies->setCollection($rows);

        $summary = [
            'total_companies' => Company::count(),
            'total_value' => (float) PurchaseOrder::whereBetween('order_date', [$start, $end])->sum('total_amount'),
            'total_returns' => PurchaseReturn::whereBetween('return_date', [$start, $end])->count(),
        ];

        return view('livewire.reports.companies', [
            'rows' => $companies,
            'summary' => $summary,
        ]);
    }
}
