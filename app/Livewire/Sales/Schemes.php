<?php

namespace App\Livewire\Sales;

use App\Models\Category;
use App\Models\Product;
use App\Models\Scheme;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Trade schemes: discounts and free goods applied automatically on sales orders. */
#[Layout('layouts.app')]
#[Title('Trade Schemes')]
class Schemes extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $type = 'discount';
    public string $audience = 'all';
    public string $target = 'product';     // product, category, all
    public ?int $product_id = null;
    public ?int $category_id = null;
    public string $min_qty = '0';
    public string $discount_percent = '0';
    public ?int $free_product_id = null;
    public string $free_qty = '1';
    public string $starts_on = '';
    public string $ends_on = '';
    public bool $active = true;

    public function mount(): void
    {
        $this->authorize('sales.view');
    }

    public function create(): void
    {
        $this->authorize('sales.approve');
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('sales.approve');
        $s = Scheme::findOrFail($id);
        $this->resetForm();
        $this->editingId = $s->id;
        $this->fill([
            'name' => $s->name, 'type' => $s->type, 'audience' => $s->audience,
            'target' => $s->product_id ? 'product' : ($s->category_id ? 'category' : 'all'),
            'product_id' => $s->product_id, 'category_id' => $s->category_id,
            'min_qty' => (string) (float) $s->min_qty, 'discount_percent' => (string) (float) $s->discount_percent,
            'free_product_id' => $s->free_product_id, 'free_qty' => (string) (float) $s->free_qty,
            'starts_on' => $s->starts_on->toDateString(), 'ends_on' => $s->ends_on->toDateString(), 'active' => $s->active,
        ]);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('sales.approve');
        $this->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|in:discount,free_goods',
            'audience' => 'required|in:all,dealers,retailers',
            'target' => 'required|in:product,category,all',
            'product_id' => 'required_if:target,product|nullable|exists:products,id',
            'category_id' => 'required_if:target,category|nullable|exists:categories,id',
            'min_qty' => $this->type === 'free_goods' ? 'required|numeric|gt:0' : 'required|numeric|min:0',
            'discount_percent' => 'required_if:type,discount|numeric|min:0|max:100',
            'free_product_id' => 'required_if:type,free_goods|nullable|exists:products,id',
            'free_qty' => 'required_if:type,free_goods|numeric|min:0',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
        ]);

        Scheme::updateOrCreate(['id' => $this->editingId], [
            'name' => $this->name,
            'type' => $this->type,
            'audience' => $this->audience,
            'product_id' => $this->target === 'product' ? $this->product_id : null,
            'category_id' => $this->target === 'category' ? $this->category_id : null,
            'min_qty' => (float) $this->min_qty,
            'discount_percent' => $this->type === 'discount' ? (float) $this->discount_percent : 0,
            'free_product_id' => $this->type === 'free_goods' ? $this->free_product_id : null,
            'free_qty' => $this->type === 'free_goods' ? (float) $this->free_qty : 0,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'active' => $this->active,
        ]);

        $this->showModal = false;
        session()->flash('success', __('Scheme saved. It applies to orders saved from now on.'));
    }

    public function toggle(int $id): void
    {
        $this->authorize('sales.approve');
        $s = Scheme::findOrFail($id);
        $s->update(['active' => ! $s->active]);
    }

    public function delete(int $id): void
    {
        $this->authorize('sales.approve');
        // Lines already ordered keep their prices; the scheme link is cleared (nullOnDelete).
        Scheme::findOrFail($id)->delete();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'type', 'audience', 'target', 'product_id', 'category_id', 'min_qty',
            'discount_percent', 'free_product_id', 'free_qty', 'active']);
        $this->starts_on = today()->toDateString();
        $this->ends_on = today()->endOfMonth()->toDateString();
        $this->resetValidation();
    }

    public function render()
    {
        $today = today()->toDateString();

        return view('livewire.sales.schemes', [
            'schemes' => Scheme::with(['product', 'category', 'freeProduct'])
                ->withCount('orderLines')
                ->orderByDesc('active')->orderByDesc('ends_on')->get(),
            'today' => $today,
            'products' => $this->showModal ? Product::sellable()->where('active', true)->orderBy('name')->get(['id', 'name', 'sku']) : collect(),
            'categories' => $this->showModal ? Category::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
