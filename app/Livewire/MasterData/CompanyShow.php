<?php

namespace App\Livewire\MasterData;

use App\Models\Company;
use App\Models\Purchase\GoodsReceipt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Company Profile')]
class CompanyShow extends Component
{
    public Company $company;

    public function render()
    {
        $products = $this->company->products()->with(['category', 'brand'])->latest()->limit(15)->get();
        $brands = $this->company->brands()->withCount('products')->latest()->limit(15)->get();
        $purchaseOrders = $this->company->purchaseOrders()->with('warehouse')->latest('order_date')->limit(15)->get();
        $goodsReceipts = GoodsReceipt::whereHas('purchaseOrder', fn ($q) => $q->where('company_id', $this->company->id))
            ->with(['purchaseOrder', 'warehouse'])
            ->latest('receipt_date')
            ->limit(15)
            ->get();
        $purchaseReturns = $this->company->purchaseReturns()->with('warehouse')->latest('return_date')->limit(15)->get();

        $summary = [
            'total_products' => $this->company->products()->count(),
            'total_brands' => $this->company->brands()->count(),
            'total_orders' => $this->company->purchaseOrders()->count(),
            'total_purchase_value' => (float) $this->company->purchaseOrders()->sum('total_amount'),
            'total_returns' => $this->company->purchaseReturns()->count(),
        ];

        return view('livewire.master-data.company-show', compact(
            'products', 'brands', 'purchaseOrders', 'goodsReceipts', 'purchaseReturns', 'summary'
        ));
    }
}
