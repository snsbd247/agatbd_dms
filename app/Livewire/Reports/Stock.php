<?php

namespace App\Livewire\Reports;

use App\Models\Batch;
use App\Models\Stock as StockModel;
use App\Models\Warehouse;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock Report')]
class Stock extends Component
{
    #[Url]
    public string $warehouse_id = '';

    public function render()
    {
        $stocks = StockModel::query()
            ->with(['product', 'warehouse'])
            ->when($this->warehouse_id, fn ($q) => $q->where('warehouse_id', $this->warehouse_id))
            ->orderBy('quantity')
            ->get();

        $nearExpiry = Batch::query()
            ->with(['product', 'warehouse'])
            ->when($this->warehouse_id, fn ($q) => $q->where('warehouse_id', $this->warehouse_id))
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [today(), today()->addDays(30)])
            ->orderBy('expiry_date')
            ->get();

        $stockValue = (float) Batch::query()
            ->when($this->warehouse_id, fn ($q) => $q->where('warehouse_id', $this->warehouse_id))
            ->selectRaw('COALESCE(SUM(quantity_available * cost_price), 0) as total')
            ->value('total');

        $lowStockCount = StockModel::query()
            ->when($this->warehouse_id, fn ($q) => $q->where('warehouse_id', $this->warehouse_id))
            ->whereColumn('quantity', '<=', 'reorder_level')->where('quantity', '>', 0)
            ->distinct()->count('product_id');

        $outOfStockCount = StockModel::query()
            ->when($this->warehouse_id, fn ($q) => $q->where('warehouse_id', $this->warehouse_id))
            ->where('quantity', '<=', 0)
            ->distinct()->count('product_id');

        return view('livewire.reports.stock', [
            'stocks' => $stocks,
            'nearExpiry' => $nearExpiry,
            'summary' => [
                'stock_value' => $stockValue,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
            ],
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }
}
