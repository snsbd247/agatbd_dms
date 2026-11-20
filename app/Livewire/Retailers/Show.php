<?php

namespace App\Livewire\Retailers;

use App\Models\Retailer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Retailer Profile')]
class Show extends Component
{
    public Retailer $retailer;

    public function render()
    {
        $this->retailer->loadMissing(['area', 'representative']);

        $orders = $this->retailer->salesOrders()->latest('order_date')->limit(15)->get();
        $invoices = $this->retailer->invoices()->latest('invoice_date')->limit(15)->get();
        $payments = $this->retailer->collections()->latest('collection_date')->limit(15)->get();
        $returns = $this->retailer->returnRequests()->latest('request_date')->limit(15)->get();
        $warrantyClaims = $this->retailer->warrantyClaims()->with('product')->latest('claim_date')->limit(15)->get();

        $summary = [
            'total_orders' => $this->retailer->salesOrders()->count(),
            'total_invoiced' => (float) $this->retailer->invoices()->sum('grand_total'),
            'total_paid' => (float) $this->retailer->collections()->sum('amount'),
            'total_returns' => $this->retailer->returnRequests()->count(),
            'total_warranty' => $this->retailer->warrantyClaims()->count(),
        ];

        return view('livewire.retailers.show', compact('orders', 'invoices', 'payments', 'returns', 'warrantyClaims', 'summary'));
    }
}
