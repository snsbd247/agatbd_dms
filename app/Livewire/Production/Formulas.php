<?php

namespace App\Livewire\Production;

use App\Models\Product;
use App\Models\Production\Bom;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Product formulas (bills of materials). A formula is never edited once a
 * batch used it: saving changes creates a new version, so old batches keep
 * the formula they were made with.
 */
#[Layout('layouts.app')]
#[Title('Formulas')]
class Formulas extends Component
{
    #[Url]
    public string $search = '';

    public bool $showModal = false;
    public ?int $editingId = null;

    public ?int $product_id = null;
    public string $name = '';
    public string $output_quantity = '';
    public string $expected_yield = '100';
    public string $notes = '';
    public bool $is_default = true;

    /** @var array<int, array{product_id: int|string|null, quantity: string, wastage_percent: string}> */
    public array $items = [];

    public function mount(): void
    {
        $this->authorize('production.view');
    }

    public function create(?int $productId = null, ?int $fromBomId = null): void
    {
        $this->authorize('production.manage');
        $this->resetErrorBag();
        $this->reset(['editingId', 'name', 'notes']);
        $this->product_id = $productId;
        $this->output_quantity = '';
        $this->expected_yield = '100';
        $this->is_default = true;
        $this->items = [['product_id' => null, 'quantity' => '', 'wastage_percent' => '0']];

        if ($fromBomId && ($from = Bom::with('items')->find($fromBomId))) {
            // A new version starting from an existing one.
            $this->product_id = $from->product_id;
            $this->name = (string) $from->name;
            $this->output_quantity = (string) (float) $from->output_quantity;
            $this->expected_yield = (string) (float) $from->expected_yield;
            $this->notes = (string) $from->notes;
            $this->items = $from->items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => (string) (float) $i->quantity, 'wastage_percent' => (string) (float) $i->wastage_percent])->all();
        }

        $this->showModal = true;
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => null, 'quantity' => '', 'wastage_percent' => '0'];
    }

    public function removeItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $this->authorize('production.manage');
        $this->validate([
            'product_id' => 'required|exists:products,id',
            'output_quantity' => 'required|numeric|gt:0',
            'expected_yield' => 'required|numeric|min:1|max:100',
            'name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|distinct|exists:products,id|different:product_id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.wastage_percent' => 'nullable|numeric|min:0|max:100',
        ], ['items.*.product_id.different' => __('A product cannot be a material of itself.')], ['items.*.product_id' => __('material')]);

        $product = Product::findOrFail($this->product_id);
        if (! in_array($product->type, ['finished_good', 'semi_finished'], true)) {
            $this->addError('product_id', __('Formulas are for finished or semi-finished products. Change the product type first.'));

            return;
        }

        DB::transaction(function () {
            $version = (int) Bom::where('product_id', $this->product_id)->max('version') + 1;
            if ($this->is_default) {
                Bom::where('product_id', $this->product_id)->where('kind', 'production')->update(['is_default' => false]);
            }
            $bom = Bom::create([
                'product_id' => $this->product_id,
                'version' => $version,
                'name' => $this->name ?: null,
                'output_quantity' => $this->output_quantity,
                'expected_yield' => $this->expected_yield,
                'notes' => $this->notes ?: null,
                'is_default' => $this->is_default || ! Bom::where('product_id', $this->product_id)->where('kind', 'production')->where('is_default', true)->exists(),
                'active' => true,
                'created_by' => auth()->id(),
            ]);
            foreach (array_values($this->items) as $i => $item) {
                $bom->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'wastage_percent' => $item['wastage_percent'] ?: 0,
                    'sort' => $i,
                ]);
            }
        });

        $this->showModal = false;
    }

    public function makeDefault(int $id): void
    {
        $this->authorize('production.manage');
        $bom = Bom::findOrFail($id);
        DB::transaction(function () use ($bom) {
            Bom::where('product_id', $bom->product_id)->where('kind', $bom->kind)->update(['is_default' => false]);
            $bom->update(['is_default' => true, 'active' => true]);
        });
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('production.manage');
        $bom = Bom::findOrFail($id);
        $bom->update(['active' => ! $bom->active, 'is_default' => $bom->active ? false : $bom->is_default]);
    }

    public function render()
    {
        $boms = Bom::with(['product.unit', 'items.product.unit'])->where('kind', 'production')
            ->when($this->search, fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%")->orWhere('sku', 'like', "%{$this->search}%")))
            ->orderBy('product_id')->orderByDesc('version')->get()
            ->groupBy('product_id');

        $chosen = $this->product_id ? Product::with('unit')->find($this->product_id) : null;
        $previewCost = 0.0;
        foreach ($this->items as $it) {
            if ($it['product_id'] && is_numeric($it['quantity'])) {
                $previewCost += (float) $it['quantity'] * (1 + (float) ($it['wastage_percent'] ?: 0) / 100) * Bom::currentCost((int) $it['product_id']);
            }
        }

        return view('livewire.production.formulas', [
            'groups' => $boms,
            'makeable' => Product::whereIn('type', ['finished_good', 'semi_finished'])->orderBy('name')->get(),
            'materials' => Product::with('unit')->whereIn('type', ['raw_material', 'packaging', 'semi_finished'])->orderBy('type')->orderBy('name')->get(),
            'chosen' => $chosen,
            'previewCost' => round($previewCost, 2),
        ]);
    }
}
