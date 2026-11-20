<?php

namespace App\Livewire\Reports;

use App\Models\Payroll\PayrollEntry;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceItem;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Profit & Loss Report')]
class Profit extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    private function costOfGoodsSold(Carbon $start, Carbon $end): float
    {
        return (float) SalesInvoiceItem::whereHas('salesInvoice', fn ($q) => $q->whereBetween('invoice_date', [$start, $end]))
            ->with(['batch', 'product'])
            ->get()
            ->sum(function (SalesInvoiceItem $item) {
                $cost = $item->batch?->cost_price ?? $item->product?->purchase_price ?? 0;

                return (float) $cost * (float) $item->quantity;
            });
    }

    public function render()
    {
        $start = Carbon::parse($this->from)->startOfDay();
        $end = Carbon::parse($this->to)->endOfDay();

        $revenue = (float) SalesInvoice::whereBetween('invoice_date', [$start, $end])->sum('grand_total');
        $cogs = $this->costOfGoodsSold($start, $end);
        $grossProfit = $revenue - $cogs;
        $payrollCost = (float) PayrollEntry::whereBetween('paid_at', [$start, $end])->sum('net_salary');
        $netProfit = $grossProfit - $payrollCost;

        $months = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $monthStart = $cursor->copy()->max($start);
            $monthEnd = $cursor->copy()->endOfMonth()->min($end);

            $monthRevenue = (float) SalesInvoice::whereBetween('invoice_date', [$monthStart, $monthEnd])->sum('grand_total');
            $monthCogs = $this->costOfGoodsSold($monthStart, $monthEnd);
            $monthPayroll = (float) PayrollEntry::whereBetween('paid_at', [$monthStart, $monthEnd])->sum('net_salary');

            $months[] = [
                'label' => $cursor->format('M Y'),
                'revenue' => $monthRevenue,
                'cogs' => $monthCogs,
                'gross_profit' => $monthRevenue - $monthCogs,
                'payroll_cost' => $monthPayroll,
                'net_profit' => ($monthRevenue - $monthCogs) - $monthPayroll,
            ];

            $cursor->addMonth();
        }

        $summary = [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'payroll_cost' => $payrollCost,
            'net_profit' => $netProfit,
        ];

        return view('livewire.reports.profit', compact('summary', 'months'));
    }
}
