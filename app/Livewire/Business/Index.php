<?php

namespace App\Livewire\Business;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Business')]
class Index extends Component
{
    public function render()
    {
        $items = collect([
            ['route' => 'purchase.orders.index', 'permission' => 'purchase.view', 'icon' => 'shopping-cart', 'color' => 'blue', 'title' => 'Purchase Orders', 'description' => 'Orders placed to companies'],
            ['route' => 'purchase.receipts.index', 'permission' => 'purchase.view', 'icon' => 'download', 'color' => 'green', 'title' => 'Goods Receipts', 'description' => 'Stock received against orders'],
            ['route' => 'purchase.returns.index', 'permission' => 'purchase.view', 'icon' => 'refresh', 'color' => 'orange', 'title' => 'Purchase Returns', 'description' => 'Stock returned to companies'],
            ['route' => 'inventory.warehouses', 'permission' => 'inventory.view', 'icon' => 'archive-box', 'color' => 'purple', 'title' => 'Warehouses', 'description' => 'Manage storage locations'],
            ['route' => 'inventory.areas', 'permission' => 'inventory.view', 'icon' => 'map-pin', 'color' => 'red', 'title' => 'Areas', 'description' => 'Manage coverage areas'],
            ['route' => 'sales.orders.index', 'permission' => 'sales.view', 'icon' => 'shopping-bag', 'color' => 'blue', 'title' => 'Sales Orders', 'description' => 'Orders placed by retailers'],
            ['route' => 'delivery.index', 'permission' => 'delivery.view', 'icon' => 'truck', 'color' => 'green', 'title' => 'Deliveries', 'description' => 'Stock shipped to retailers'],
            ['route' => 'delivery.invoices.index', 'permission' => 'delivery.view', 'icon' => 'document-text', 'color' => 'indigo', 'title' => 'Delivery Invoices', 'description' => 'Printable invoice per delivery'],
            ['route' => 'collection.index', 'permission' => 'collection.view', 'icon' => 'banknotes', 'color' => 'purple', 'title' => 'Collections', 'description' => 'Payments collected from retailers'],
            ['route' => 'reports.stock', 'permission' => 'reports.view', 'icon' => 'chart-bar', 'color' => 'orange', 'title' => 'Stock Overview', 'description' => 'Current stock levels & alerts'],
        ])->filter(fn ($item) => auth()->user()->can($item['permission']))->values();

        return view('livewire.business.index', compact('items'));
    }
}
