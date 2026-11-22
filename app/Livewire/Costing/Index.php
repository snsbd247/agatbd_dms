<?php

namespace App\Livewire\Costing;

use App\Models\Costing\CostSheet;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Cost sheets: every product's declared cost and prices, and the drafts waiting. */
#[Layout('layouts.app')]
#[Title('Cost Sheets')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public ?int $newProduct = null;

    public function mount(): void
    {
        $this->authorize('costing.view');
    }

    public function updating($name): void
    {
        in_array($name, ['status', 'search'], true) && $this->resetPage();
    }

    public function start()
    {
        $this->authorize('costing.manage');
        $this->validate(['newProduct' => 'required|exists:products,id'], [], ['newProduct' => __('product')]);
        $draft = CostSheet::where('product_id', $this->newProduct)->where('status', 'draft')->latest('id')->first();

        return $draft
            ? $this->redirectRoute('costing.sheets.show', $draft)
            : $this->redirectRoute('costing.sheets.create', ['product' => $this->newProduct]);
    }

    public function render()
    {
        $sheets = CostSheet::with('product.unit')
            ->when($this->status, fn ($q) => $q->where('status', $this->status), fn ($q) => $q->whereIn('status', ['draft', 'approved', 'applied']))
            ->when($this->search, fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%")->orWhere('sku', 'like', "%{$this->search}%")))
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->latest('updated_at')
            ->paginate(25);

        $sellable = Product::where('active', true)->whereNotIn('type', ['raw_material', 'packaging']);

        return view('livewire.costing.index', [
            'sheets' => $sheets,
            'products' => (clone $sellable)->orderBy('name')->get(['id', 'name', 'sku']),
            'withoutSheet' => (clone $sellable)->whereDoesntHave('costSheets', fn ($q) => $q->where('status', 'applied'))->count(),
            'waiting' => CostSheet::where('status', 'draft')->count(),
        ]);
    }
}
