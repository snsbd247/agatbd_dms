<?php

namespace App\Livewire\Kits;

use App\Models\Accounts\Account;
use App\Models\Kits\AssemblyOrder;
use App\Models\Product;
use App\Models\Production\Bom;
use App\Models\Warehouse;
use App\Services\Kits\AssemblyService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Kit / pack recipes and repacking, and making them. */
#[Layout('layouts.app')]
#[Title('Kits & Repacking')]
class Index extends Component
{
    #[Url]
    public string $tab = 'recipes';

    // recipe form
    public bool $showRecipe = false;
    public string $kind = 'kit';
    public ?int $product_id = null;
    public string $output_quantity = '1';
    public string $name = '';
    public array $items = [];

    // assemble form
    public bool $showAssemble = false;
    public ?int $bom_id = null;
    public string $quantity = '';
    public ?int $warehouse_id = null;
    public string $assembly_date = '';
    public string $labour = '0';
    public ?int $paid_from = null;
    public string $batch_no = '';
    public string $notes = '';

    public ?string $message = null;

    public function mount(): void
    {
        $this->authorize('kits.view');
        in_array($this->tab, ['recipes', 'made'], true) || $this->tab = 'recipes';
    }

    public function newRecipe(?int $fromId = null): void
    {
        $this->authorize('kits.manage');
        $this->reset(['product_id', 'name']);
        $this->kind = 'kit';
        $this->output_quantity = '1';
        $this->items = [['product_id' => '', 'quantity' => '1', 'wastage_percent' => '0']];
        if ($fromId && ($b = Bom::with('items')->whereIn('kind', ['kit', 'repack'])->find($fromId))) {
            $this->kind = $b->kind;
            $this->product_id = $b->product_id;
            $this->output_quantity = (string) (float) $b->output_quantity;
            $this->name = (string) $b->name;
            $this->items = $b->items->map(fn ($i) => ['product_id' => (string) $i->product_id, 'quantity' => (string) (float) $i->quantity, 'wastage_percent' => (string) (float) $i->wastage_percent])->all();
        }
        $this->resetErrorBag();
        $this->showRecipe = true;
    }

    public function addLine(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => '1', 'wastage_percent' => '0'];
    }

    public function removeLine(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    public function saveRecipe(): void
    {
        $this->authorize('kits.manage');
        $this->validate([
            'kind' => 'required|in:kit,repack',
            'product_id' => 'required|exists:products,id',
            'output_quantity' => 'required|numeric|gt:0',
            'name' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|distinct|exists:products,id|different:product_id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.wastage_percent' => 'required|numeric|min:0|max:100',
        ], [
            'items.*.product_id.different' => __('The pack cannot be its own component.'),
        ], ['product_id' => __('product'), 'items.*.product_id' => __('component')]);

        DB::transaction(function () {
            Bom::where('product_id', $this->product_id)->whereIn('kind', ['kit', 'repack'])->update(['is_default' => false]);
            $bom = Bom::create([
                'product_id' => $this->product_id,
                'kind' => $this->kind,
                'version' => (int) Bom::where('product_id', $this->product_id)->max('version') + 1,
                'name' => $this->name ?: null,
                'output_quantity' => $this->output_quantity,
                'expected_yield' => 100,
                'is_default' => true,
                'active' => true,
                'created_by' => auth()->id(),
            ]);
            foreach (array_values($this->items) as $i => $item) {
                $bom->items()->create(['product_id' => $item['product_id'], 'quantity' => $item['quantity'], 'wastage_percent' => $item['wastage_percent'] ?: 0, 'sort' => $i]);
            }
        });
        $this->showRecipe = false;
        $this->message = __('Recipe saved. The newest recipe of a product is the one used.');
    }

    public function newAssembly(?int $bomId = null): void
    {
        $this->authorize('kits.manage');
        $this->reset(['quantity', 'batch_no', 'notes']);
        $this->bom_id = $bomId;
        $this->labour = '0';
        $this->assembly_date = today()->toDateString();
        $this->warehouse_id ??= Warehouse::whereNull('dealer_id')->where('active', true)->whereIn('purpose', ['general', 'finished_goods'])->orderBy('id')->value('id');
        $this->paid_from ??= Account::where('system_key', 'cash')->value('id');
        $this->resetErrorBag();
        $this->showAssemble = true;
    }

    public function assemble(AssemblyService $service): void
    {
        $this->authorize('kits.manage');
        $this->validate([
            'bom_id' => 'required|exists:boms,id',
            'quantity' => 'required|numeric|gt:0',
            'warehouse_id' => 'required|exists:warehouses,id',
            'assembly_date' => 'required|date',
            'labour' => 'required|numeric|min:0',
            'batch_no' => 'nullable|string|max:100',
        ], [], ['bom_id' => __('recipe')]);

        try {
            $order = $service->assemble(Bom::findOrFail($this->bom_id), (float) $this->quantity, (int) $this->warehouse_id, $this->assembly_date,
                (float) $this->labour, $this->paid_from, $this->batch_no ?: null, $this->notes ?: null);
        } catch (\RuntimeException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }
        $this->showAssemble = false;
        $this->tab = 'made';
        $this->message = __(':n made: :q of :p, batch :b, ৳ :c each.', ['n' => $order->number, 'q' => (float) $order->quantity, 'p' => $order->product->name, 'b' => $order->batch_no, 'c' => number_format((float) $order->unit_cost, 2)]);
    }

    public function render()
    {
        $recipes = Bom::with(['product.unit', 'items.product.unit'])->whereIn('kind', ['kit', 'repack'])->where('is_default', true)->orderBy('kind')->get();

        return view('livewire.kits.index', [
            'recipes' => $recipes,
            'orders' => AssemblyOrder::with(['product.unit', 'warehouse', 'items.product'])->latest('assembly_date')->latest('id')->limit(50)->get(),
            'outputs' => Product::where('active', true)->whereNotIn('type', ['raw_material'])->orderBy('name')->get(['id', 'name', 'sku']),
            'components' => Product::with('unit')->where('active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit_id']),
            'warehouses' => Warehouse::whereNull('dealer_id')->where('active', true)->whereNotIn('purpose', ['damage'])->orderBy('name')->get(),
            'money' => Account::money()->orderBy('code')->get(),
        ]);
    }
}
