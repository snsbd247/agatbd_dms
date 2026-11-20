<?php

namespace App\Livewire\Representatives;

use App\Models\PaymentCollection;
use App\Models\Payroll\Commission;
use App\Models\Representative;
use App\Models\Sales\SalesInvoice;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Representative Profile')]
class Show extends Component
{
    public Representative $representative;

    public string $period = '';

    public function mount(Representative $representative): void
    {
        $this->representative = $representative;
        $this->period = today()->format('Y-m');
    }

    public function render()
    {
        $this->representative->loadMissing('area');

        $retailers = $this->representative->retailers()->with('area')->orderBy('name')->get();
        $orders = $this->representative->salesOrders()->with('retailer')->latest('order_date')->limit(15)->get();
        $payrollEntries = $this->representative->payrollEntries()->latest('period')->limit(6)->get();

        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $periodSales = (float) SalesInvoice::whereHas('salesOrder', fn ($q) => $q->where('representative_id', $this->representative->id))
            ->whereBetween('invoice_date', [$start, $end])
            ->sum('grand_total');

        $periodCollection = $this->representative->user_id
            ? (float) PaymentCollection::where('collected_by', $this->representative->user_id)->whereBetween('collection_date', [$start, $end])->sum('amount')
            : 0.0;

        $target = (float) $this->representative->target_amount;
        $achievement = $target > 0 ? min(100, round(($periodSales / $target) * 100, 1)) : 0;
        $commission = Commission::where('representative_id', $this->representative->id)->where('period', $this->period)->first();

        $summary = [
            'total_retailers' => $retailers->count(),
            'total_orders' => $this->representative->salesOrders()->count(),
            'total_due' => (float) $retailers->sum('current_due'),
            'period_sales' => $periodSales,
            'period_collection' => $periodCollection,
            'achievement' => $achievement,
            'commission' => $commission?->commission_amount ?? 0,
        ];

        return view('livewire.representatives.show', compact('retailers', 'orders', 'payrollEntries', 'summary'));
    }
}
