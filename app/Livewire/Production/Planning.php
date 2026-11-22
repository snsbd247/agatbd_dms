<?php

namespace App\Livewire\Production;

use App\Models\Batch;
use App\Models\Factory;
use App\Models\Product;
use App\Models\Production\Bom;
use App\Models\Production\ProductionIssue;
use App\Models\Production\ProductionOrder;
use App\Services\Production\ProductionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Material planning: what the open batches plus a planned production list
 * need, against what is in the factory's raw-material store and on order.
 */
#[Layout('layouts.app')]
#[Title('Material Planning')]
class Planning extends Component
{
    public ?int $factory_id = null;

    /** @var array<int, array{product_id: int|string|null, quantity: string}> */
    public array $plan = [];

    public function mount(): void
    {
        $this->authorize('production.view');
        $this->factory_id = Factory::where('active', true)->orderBy('name')->value('id');
        $this->plan = [['product_id' => null, 'quantity' => '']];
    }

    public function addRow(): void
    {
        $this->plan[] = ['product_id' => null, 'quantity' => ''];
    }

    public function removeRow(int $i): void
    {
        unset($this->plan[$i]);
        $this->plan = array_values($this->plan);
    }

    public function render()
    {
        $need = [];          // product id => [open, planned]
        $add = function (int $pid, float $q, string $kind) use (&$need) {
            $need[$pid] ??= ['open' => 0.0, 'planned' => 0.0];
            $need[$pid][$kind] += $q;
        };

        // Still to issue for batches already open in this factory.
        $open = ProductionOrder::with('bom.items')->where('factory_id', $this->factory_id)->whereIn('status', ['planned', 'in_progress', 'qc'])->get();
        $issued = ProductionIssue::whereIn('production_order_id', $open->pluck('id'))
            ->selectRaw('production_order_id, product_id, SUM(quantity) as q')->groupBy('production_order_id', 'product_id')->get()
            ->groupBy('production_order_id');
        foreach ($open as $o) {
            $done = ($issued[$o->id] ?? collect())->pluck('q', 'product_id');
            foreach ($o->bom->requirements((float) $o->planned_quantity) as $pid => $q) {
                $add($pid, max(0, $q - (float) ($done[$pid] ?? 0)), 'open');
            }
        }

        // The planned list, using each product's default formula.
        foreach ($this->plan as $row) {
            if ($row['product_id'] && is_numeric($row['quantity']) && (float) $row['quantity'] > 0) {
                $bom = Bom::with('items')->where('product_id', $row['product_id'])->where('kind', 'production')->where('active', true)->orderByDesc('is_default')->orderByDesc('version')->first();
                foreach ($bom?->requirements((float) $row['quantity']) ?? [] as $pid => $q) {
                    $add($pid, $q, 'planned');
                }
            }
        }

        $raw = $this->factory_id ? ProductionService::store($this->factory_id, 'raw_material') : null;
        $stock = $raw ? Batch::where('warehouse_id', $raw->id)->where('quantity_available', '>', 0)->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_available) as q')->pluck('q', 'product_id') : collect();

        // Ordered from suppliers but not yet received.
        $onOrder = \App\Models\Purchase\PurchaseOrderItem::whereHas('purchaseOrder', fn ($p) => $p->whereIn('status', ['draft', 'sent', 'partial']))
            ->whereIn('product_id', array_keys($need))->get()
            ->groupBy('product_id')->map(fn ($g) => $g->sum(fn ($i) => max(0, (float) $i->quantity - (float) ($i->received_quantity ?? 0))));

        $products = Product::with('unit')->whereIn('id', array_keys($need))->get()->keyBy('id');
        $rows = collect($need)->map(function ($n, $pid) use ($stock, $onOrder, $products) {
            $total = $n['open'] + $n['planned'];
            $have = (float) ($stock[$pid] ?? 0);
            $ordered = (float) ($onOrder[$pid] ?? 0);

            return [
                'product' => $products[$pid] ?? null,
                'open' => round($n['open'], 3),
                'planned' => round($n['planned'], 3),
                'total' => round($total, 3),
                'stock' => round($have, 3),
                'on_order' => round($ordered, 3),
                'short' => round(max(0, $total - $have - $ordered), 3),
                'cost' => round(max(0, $total - $have - $ordered) * Bom::currentCost((int) $pid), 2),
            ];
        })->filter(fn ($r) => $r['product'])->sortByDesc('short')->values();

        return view('livewire.production.planning', [
            'factories' => Factory::where('active', true)->orderBy('name')->get(),
            'makeable' => Product::whereIn('type', ['finished_good', 'semi_finished'])->whereHas('boms', fn ($b) => $b->where('kind', 'production')->where('active', true))->orderBy('name')->get(),
            'rows' => $rows,
            'openCount' => $open->count(),
            'raw' => $raw,
        ]);
    }
}
