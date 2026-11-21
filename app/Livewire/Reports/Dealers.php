<?php

namespace App\Livewire\Reports;

use App\Services\Reports\DealerSalesReport;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Head office: primary (to dealers) and secondary (dealers to their customers) sales. */
#[Layout('layouts.app')]
#[Title('Dealer Sales Report')]
class Dealers extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('dealers.view');
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function render(DealerSalesReport $report)
    {
        $rows = $report->rows(Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay());

        return view('livewire.reports.dealers', [
            'rows' => $rows,
            'totals' => $report->totals($rows),
        ]);
    }
}
