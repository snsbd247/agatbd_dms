<?php

namespace App\Livewire\Reports;

use App\Models\Batch;
use App\Models\Warehouse;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Expiry Report')]
class Expiry extends Component
{
    use WithPagination;

    #[Url]
    public string $warehouse_id = '';

    #[Url]
    public string $within_days = '90';

    public function updated(): void
    {
        $this->resetPage();
    }

    private function baseQuery()
    {
        return Batch::query()
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expiry_date')
            ->when($this->warehouse_id, fn ($q) => $q->where('warehouse_id', $this->warehouse_id))
            ->when($this->within_days, fn ($q) => $q->where('expiry_date', '<=', today()->addDays((int) $this->within_days)));
    }

    public function render()
    {
        $batches = $this->baseQuery()
            ->with(['product', 'warehouse'])
            ->orderBy('expiry_date')
            ->paginate(15);

        $summaryQuery = $this->baseQuery();
        $summary = [
            'batch_count' => (clone $summaryQuery)->count(),
            'expired_count' => (clone $summaryQuery)->where('expiry_date', '<', today())->count(),
            'stock_value' => (float) (clone $summaryQuery)->selectRaw('COALESCE(SUM(quantity_available * cost_price), 0) as total')->value('total'),
        ];

        return view('livewire.reports.expiry', [
            'batches' => $batches,
            'summary' => $summary,
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }
}
