<?php

namespace App\Livewire\MasterData;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Products')]
class Products extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public bool $showViewModal = false;
    public ?int $editingId = null;
    public ?int $viewingId = null;

    public ?int $form_company_id = null;
    public ?int $form_brand_id = null;
    public ?int $form_category_id = null;
    public ?int $form_unit_id = null;
    public string $sku = '';
    public string $barcode = '';
    public string $name = '';
    public string $description = '';
    public string $purchase_price = '0';
    public string $sale_price = '0';
    public string $reorder_level = '0';
    public string $type = 'trading';
    public string $shelf_life_days = '';
    public string $vat_rate = '0';
    public bool $active = true;

    /** Extra packs: [['unit_id' => int|null, 'factor' => string], …] */
    public array $packs = [];

    #[Url(except: '')]
    public string $search = '';
    public string $filter_category_id = '';
    public string $filter_brand_id = '';
    public string $filter_company_id = '';
    public string $filter_type = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_category_id', 'filter_brand_id', 'filter_company_id', 'filter_type']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('master-data.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function view(int $id): void
    {
        $this->viewingId = $id;
        $this->showViewModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('master-data.manage');
        $product = Product::findOrFail($id);
        $this->editingId = $product->id;
        $this->form_company_id = $product->company_id;
        $this->form_brand_id = $product->brand_id;
        $this->form_category_id = $product->category_id;
        $this->form_unit_id = $product->unit_id;
        $this->sku = $product->sku;
        $this->barcode = (string) $product->barcode;
        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->purchase_price = (string) $product->purchase_price;
        $this->sale_price = (string) $product->sale_price;
        $this->reorder_level = (string) $product->reorder_level;
        $this->type = $product->type ?? 'trading';
        $this->shelf_life_days = (string) ($product->shelf_life_days ?? '');
        $this->vat_rate = rtrim(rtrim((string) $product->vat_rate, '0'), '.') ?: '0';
        $this->active = $product->active;
        $this->packs = $product->units()->get()
            ->map(fn (ProductUnit $u) => ['unit_id' => $u->unit_id, 'factor' => rtrim(rtrim(number_format((float) $u->factor, 6, '.', ''), '0'), '.')])
            ->all();
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('master-data.manage');

        $data = $this->validate([
            'form_company_id' => 'required|exists:companies,id',
            'form_brand_id' => 'nullable|exists:brands,id',
            'form_category_id' => 'required|exists:categories,id',
            'form_unit_id' => 'required|exists:units,id',
            'sku' => 'required|string|max:100|unique:products,sku,'.($this->editingId ?? 'NULL').',id',
            'barcode' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'reorder_level' => 'required|numeric|min:0',
            'type' => 'required|in:'.implode(',', array_keys(Product::TYPES)),
            'shelf_life_days' => 'nullable|integer|min:1|max:3650',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'active' => 'boolean',
            'packs' => 'array',
            'packs.*.unit_id' => 'required|distinct|exists:units,id|different:form_unit_id',
            'packs.*.factor' => 'required|numeric|gt:0',
        ], [
            'packs.*.unit_id.different' => __('A pack cannot use the product\'s own base unit.'),
            'packs.*.unit_id.distinct' => __('Each pack unit can be used once.'),
        ], [
            'packs.*.unit_id' => __('pack unit'),
            'packs.*.factor' => __('pack size'),
        ]);

        $productData = [
            'company_id' => $data['form_company_id'],
            'brand_id' => $data['form_brand_id'],
            'category_id' => $data['form_category_id'],
            'unit_id' => $data['form_unit_id'],
            'sku' => $data['sku'],
            'barcode' => $data['barcode'],
            'name' => $data['name'],
            'description' => $data['description'],
            'purchase_price' => $data['purchase_price'],
            'sale_price' => $data['sale_price'],
            'reorder_level' => $data['reorder_level'],
            'type' => $data['type'],
            'shelf_life_days' => $data['shelf_life_days'] !== '' ? (int) $data['shelf_life_days'] : null,
            'vat_rate' => $data['vat_rate'],
            'active' => $data['active'],
        ];

        $existing = $this->editingId ? Product::find($this->editingId) : null;
        $priceChanged = ! $existing
            || bccomp((string) $existing->purchase_price, (string) $productData['purchase_price'], 2) !== 0
            || bccomp((string) $existing->sale_price, (string) $productData['sale_price'], 2) !== 0;

        $product = Product::updateOrCreate(['id' => $this->editingId], $productData);

        // Sync packs: keep, update or remove rows so the audit log shows real changes.
        $wanted = collect($data['packs'])->keyBy('unit_id');
        foreach ($product->units()->get() as $existingPack) {
            if (! $wanted->has($existingPack->unit_id)) {
                $existingPack->delete();
            }
        }
        foreach ($wanted as $unitId => $pack) {
            ProductUnit::updateOrCreate(['product_id' => $product->id, 'unit_id' => $unitId], ['factor' => $pack['factor']]);
        }

        if ($priceChanged) {
            ProductPrice::create([
                'product_id' => $product->id,
                'purchase_price' => $productData['purchase_price'],
                'sale_price' => $productData['sale_price'],
                'effective_from' => today(),
                'created_by' => Auth::id(),
            ]);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('master-data.manage');
        Product::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'form_company_id', 'form_brand_id', 'form_category_id', 'form_unit_id',
            'sku', 'barcode', 'name', 'description',
        ]);
        $this->purchase_price = '0';
        $this->sale_price = '0';
        $this->reorder_level = '0';
        $this->type = 'trading';
        $this->shelf_life_days = '';
        $this->vat_rate = '0';
        $this->packs = [];
        $this->active = true;
        $this->resetErrorBag();
    }

    public function addPack(): void
    {
        $this->packs[] = ['unit_id' => null, 'factor' => ''];
    }

    public function removePack(int $index): void
    {
        unset($this->packs[$index]);
        $this->packs = array_values($this->packs);
    }

    private function filteredQuery()
    {
        return Product::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")
                ->orWhere('barcode', 'like', "%{$this->search}%")))
            ->when($this->filter_type, fn ($q) => $q->where('type', $this->filter_type))
            ->when($this->filter_category_id, fn ($q) => $q->where('category_id', $this->filter_category_id))
            ->when($this->filter_brand_id, fn ($q) => $q->where('brand_id', $this->filter_brand_id))
            ->when($this->filter_company_id, fn ($q) => $q->where('company_id', $this->filter_company_id));
    }

    public function render()
    {
        $products = $this->filteredQuery()
            ->with(['company', 'brand', 'category', 'unit'])
            ->withSum('stocks', 'quantity')
            ->orderBy('name')
            ->paginate(10);

        $allProducts = Product::withSum('stocks', 'quantity')->get(['id', 'reorder_level', 'active']);
        $lowStock = $allProducts->filter(fn ($p) => (float) $p->stocks_sum_quantity > 0 && (float) $p->stocks_sum_quantity <= (float) $p->reorder_level)->count();
        $outOfStock = $allProducts->filter(fn ($p) => (float) ($p->stocks_sum_quantity ?? 0) <= 0)->count();

        $summary = [
            'total' => $allProducts->count(),
            'active' => $allProducts->where('active', true)->count(),
            'inactive' => $allProducts->where('active', false)->count(),
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
        ];

        return view('livewire.master-data.products', [
            'products' => $products,
            'companies' => Company::orderBy('name')->get(),
            'brands' => $this->form_company_id ? Brand::where('company_id', $this->form_company_id)->orderBy('name')->get() : Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'summary' => $summary,
            'viewingProduct' => $this->viewingId ? Product::with(['company', 'brand', 'category', 'unit', 'units.unit'])->find($this->viewingId) : null,
        ]);
    }
}
