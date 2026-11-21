<?php

namespace App\Livewire\Dashboard;

use App\Models\Returns\ReturnRequest;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Dashboard')]
class Retailer extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('Retailer'), 403);
    }

    public function render()
    {
        $retailer = Auth::user()->customerAccount();

        if (! $retailer) {
            return view('livewire.dashboard.retailer', [
                'retailer' => null,
                'stats' => array_fill_keys(['my_orders', 'pending_orders', 'due', 'credit_limit', 'pending_returns'], 0),
                'recentOrders' => collect(),
                'recentInvoices' => collect(),
            ]);
        }

        $stats = [
            'my_orders' => SalesOrder::where('retailer_id', $retailer->id)->count(),
            'pending_orders' => SalesOrder::where('retailer_id', $retailer->id)->where('status', 'pending')->count(),
            'due' => (float) $retailer->current_due,
            'credit_limit' => (float) $retailer->credit_limit,
            'pending_returns' => ReturnRequest::where('retailer_id', $retailer->id)->where('status', 'pending')->count(),
        ];

        $recentOrders = SalesOrder::where('retailer_id', $retailer->id)->latest('order_date')->limit(5)->get();
        $recentInvoices = SalesInvoice::where('retailer_id', $retailer->id)->latest('invoice_date')->limit(5)->get();

        return view('livewire.dashboard.retailer', [
            'retailer' => $retailer,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'recentInvoices' => $recentInvoices,
        ]);
    }
}
