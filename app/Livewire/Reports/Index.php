<?php

namespace App\Livewire\Reports;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Reports')]
class Index extends Component
{
    public function render()
    {
        $reports = [
            ['route' => 'reports.sales', 'icon' => 'chart-bar', 'color' => 'blue', 'title' => 'Sales Report', 'description' => 'View sales performance'],
            ['route' => 'reports.purchase', 'icon' => 'shopping-cart', 'color' => 'indigo', 'title' => 'Purchase Report', 'description' => 'View purchase order performance'],
            ['route' => 'reports.collections', 'icon' => 'banknotes', 'color' => 'green', 'title' => 'Collection Report', 'description' => 'View collection summary'],
            ['route' => 'reports.stock', 'icon' => 'archive-box', 'color' => 'purple', 'title' => 'Stock Report', 'description' => 'View stock summary'],
            ['route' => 'reports.expiry', 'icon' => 'clock', 'color' => 'orange', 'title' => 'Expiry Report', 'description' => 'View batches nearing expiry'],
            ['route' => 'reports.returns', 'icon' => 'refresh', 'color' => 'orange', 'title' => 'Return Report', 'description' => 'View return summary'],
            ['route' => 'reports.warranty', 'icon' => 'shield-exclamation', 'color' => 'red', 'title' => 'Warranty Report', 'description' => 'View warranty claim summary'],
            ['route' => 'reports.representatives', 'icon' => 'user-group', 'color' => 'red', 'title' => 'Representative Report', 'description' => 'View representative performance'],
            ['route' => 'reports.retailers', 'icon' => 'building-storefront', 'color' => 'cyan', 'title' => 'Retailer Report', 'description' => 'View retailer-wise summary'],
            ['route' => 'reports.companies', 'icon' => 'building-storefront', 'color' => 'teal', 'title' => 'Company Report', 'description' => 'View company-wise purchase summary'],
            ['route' => 'reports.areas', 'icon' => 'map-pin', 'color' => 'purple', 'title' => 'Area Report', 'description' => 'View area-wise sales & coverage'],
            ['route' => 'reports.products', 'icon' => 'archive-box', 'color' => 'pink', 'title' => 'Product Performance', 'description' => 'View top products by revenue & profit'],
            ['route' => 'reports.payroll', 'icon' => 'wallet', 'color' => 'purple', 'title' => 'Payroll & Commission', 'description' => 'View payroll summary across periods'],
            ['route' => 'reports.profit', 'icon' => 'trending-up', 'color' => 'green', 'title' => 'Profit & Loss Report', 'description' => 'View revenue, cost & profit'],
        ];

        return view('livewire.reports.index', compact('reports'));
    }
}
