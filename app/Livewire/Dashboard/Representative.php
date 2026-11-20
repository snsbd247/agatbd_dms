<?php

namespace App\Livewire\Dashboard;

use App\Models\Delivery\DeliveryNote;
use App\Models\Payroll\Commission;
use App\Models\PaymentCollection;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Representative Dashboard')]
class Representative extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('Sales Representative'), 403);
    }

    public function render()
    {
        $representative = Auth::user()->representative;
        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();
        $period = today()->format('Y-m');

        if (! $representative) {
            return view('livewire.dashboard.representative', [
                'representative' => null,
                'stats' => array_fill_keys(['today_orders', 'monthly_sales', 'commission', 'collection', 'target', 'achievement'], 0),
                'myRetailersCount' => 0,
                'pendingOrders' => collect(),
                'recentDeliveries' => collect(),
            ]);
        }

        $monthlySales = SalesInvoice::whereHas('salesOrder', fn ($q) => $q->where('representative_id', $representative->id))
            ->whereBetween('invoice_date', [$monthStart, $monthEnd])
            ->sum('grand_total');

        $stats = [
            'today_orders' => SalesOrder::where('representative_id', $representative->id)->whereDate('order_date', today())->count(),
            'monthly_sales' => (float) $monthlySales,
            'commission' => (float) Commission::where('representative_id', $representative->id)->where('period', $period)->sum('commission_amount'),
            'collection' => $representative->user_id
                ? (float) PaymentCollection::where('collected_by', $representative->user_id)->whereBetween('collection_date', [$monthStart, $monthEnd])->sum('amount')
                : 0.0,
            'target' => (float) $representative->target_amount,
        ];
        $stats['achievement'] = $stats['target'] > 0 ? min(100, round(($stats['monthly_sales'] / $stats['target']) * 100)) : 0;

        $myRetailersCount = Retailer::where('representative_id', $representative->id)->count();

        $pendingOrders = SalesOrder::where('representative_id', $representative->id)
            ->whereIn('status', ['pending', 'credit_hold', 'approved', 'partial'])
            ->with('retailer')
            ->latest('order_date')
            ->limit(5)
            ->get();

        $recentDeliveries = DeliveryNote::whereHas('salesOrder', fn ($q) => $q->where('representative_id', $representative->id))
            ->with('salesOrder.retailer')
            ->latest('delivery_date')
            ->limit(5)
            ->get();

        return view('livewire.dashboard.representative', [
            'representative' => $representative,
            'stats' => $stats,
            'myRetailersCount' => $myRetailersCount,
            'pendingOrders' => $pendingOrders,
            'recentDeliveries' => $recentDeliveries,
        ]);
    }
}
