<?php

namespace App\Livewire\Production;

use App\Models\Factory;
use App\Models\Production\ProductionOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Production report: output, yield and cost per product, standard vs actual. */
#[Layout('layouts.app')]
#[Title('Production Report')]
class Report extends Component
{
    #[Url]
    public string $from = '';
    #[Url]
    public string $to = '';
    #[Url]
    public string $factory = '';

    public function mount(): void
    {
        $this->authorize('production.view');
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function render()
    {
        $orders = ProductionOrder::with(['product.unit', 'factory'])->where('status', 'completed')
            ->whereDate('mfg_date', '>=', $this->from)->whereDate('mfg_date', '<=', $this->to)
            ->when($this->factory, fn ($q) => $q->where('factory_id', $this->factory))
            ->orderBy('mfg_date')->get();

        $byProduct = $orders->groupBy('product_id')->map(function ($g) {
            $planned = (float) $g->sum('planned_quantity');
            $good = (float) $g->sum('good_quantity');
            $total = (float) $g->sum('total_cost');
            $material = (float) $g->sum('material_cost');
            $standard = (float) $g->sum('standard_cost');

            return [
                'product' => $g->first()->product,
                'batches' => $g->count(),
                'planned' => $planned,
                'good' => $good,
                'rejected' => (float) $g->sum('rejected_quantity'),
                'yield' => $planned > 0 ? round($good / $planned * 100, 1) : 0,
                'material' => $material,
                'labour' => (float) $g->sum('labour_cost'),
                'overhead' => (float) $g->sum('overhead_cost'),
                'total' => $total,
                'unit' => $good > 0 ? round($total / $good, 2) : 0,
                'standard' => $standard,
                'variance' => round($material - $standard, 2),
            ];
        })->sortByDesc('total')->values();

        return view('livewire.production.report', [
            'rows' => $byProduct,
            'orders' => $orders,
            'factories' => Factory::orderBy('name')->get(),
            'open' => ProductionOrder::whereIn('status', ['planned', 'in_progress', 'qc'])->count(),
            'wip' => (float) ProductionOrder::whereIn('status', ['in_progress', 'qc'])->sum('material_cost'),
        ]);
    }
}
