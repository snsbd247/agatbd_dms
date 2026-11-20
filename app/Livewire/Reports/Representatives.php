<?php

namespace App\Livewire\Reports;

use App\Models\PaymentCollection;
use App\Models\Payroll\Commission;
use App\Models\Representative;
use App\Models\Sales\SalesInvoice;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Representative Performance Report')]
class Representatives extends Component
{
    #[Url]
    public string $period = '';

    public function mount(): void
    {
        $this->period = $this->period ?: today()->format('Y-m');
    }

    public function render()
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $commissions = Commission::where('period', $this->period)->get()->keyBy('representative_id');

        $rows = Representative::where('active', true)->get()->map(function (Representative $rep) use ($start, $end, $commissions) {
            $sales = (float) SalesInvoice::whereHas('salesOrder', fn ($q) => $q->where('representative_id', $rep->id))
                ->whereBetween('invoice_date', [$start, $end])
                ->sum('grand_total');

            $collection = $rep->user_id
                ? (float) PaymentCollection::where('collected_by', $rep->user_id)->whereBetween('collection_date', [$start, $end])->sum('amount')
                : 0.0;

            $target = (float) $rep->target_amount;
            $achievement = $target > 0 ? min(100, round(($sales / $target) * 100, 1)) : 0;

            return [
                'representative' => $rep,
                'sales' => $sales,
                'collection' => $collection,
                'target' => $target,
                'achievement' => $achievement,
                'commission' => $commissions->get($rep->id),
            ];
        })->sortByDesc('sales')->values();

        $summary = [
            'total_sales' => (float) $rows->sum('sales'),
            'total_collection' => (float) $rows->sum('collection'),
            'avg_achievement' => $rows->count() > 0 ? round($rows->avg('achievement'), 1) : 0,
        ];

        return view('livewire.reports.representatives', [
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }
}
