<?php

namespace App\Livewire\MasterData;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Master Data')]
class Index extends Component
{
    public function render()
    {
        $items = [
            ['route' => 'master-data.companies', 'icon' => 'building-storefront', 'color' => 'blue', 'title' => 'Companies', 'description' => 'Manage supplier companies'],
            ['route' => 'master-data.brands', 'icon' => 'gift', 'color' => 'purple', 'title' => 'Brands', 'description' => 'Manage product brands'],
            ['route' => 'master-data.categories', 'icon' => 'grid', 'color' => 'orange', 'title' => 'Categories', 'description' => 'Manage product categories'],
            ['route' => 'master-data.units', 'icon' => 'database', 'color' => 'green', 'title' => 'Units', 'description' => 'Manage units of measure'],
            ['route' => 'master-data.products', 'icon' => 'archive-box', 'color' => 'indigo', 'title' => 'Products', 'description' => 'Manage your product catalog'],
        ];

        return view('livewire.master-data.index', compact('items'));
    }
}
