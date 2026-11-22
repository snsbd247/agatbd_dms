<?php

namespace App\Livewire\Costing;

use App\Models\Costing\CostHead;
use App\Models\Costing\CostSheet;
use App\Models\Product;
use App\Services\Costing\CostingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** One cost sheet: build a draft, approve or reject it, or look at an old one. */
#[Layout('layouts.app')]
#[Title('Cost Sheet')]
class Sheet extends Component
{
    public ?CostSheet $sheet = null;

    #[Url]
    public ?int $product = null;

    #[Url]
    public ?int $from = null;   // copy overheads and margins from this sheet

    public string $basis = 'purchase';
    public ?int $bom_id = null;
    public array $materials = [];
    public array $overheads = [];
    public string $company_margin = '10';
    public string $dealer_margin = '8';
    public string $retailer_margin = '10';
    public string $dealer_price = '0';
    public string $retailer_price = '0';
    public string $mrp = '0';
    public bool $manualPrices = false;
    public string $effective_from = '';
    public string $first_supply_date = '';
    public string $notes = '';
    public ?int $addHead = null;

    public function mount(CostingService $costing, ?CostSheet $sheet = null): void
    {
        $this->authorize('costing.view');
        $this->effective_from = today()->toDateString();

        if ($sheet?->exists) {
            $this->sheet = $sheet->load('items', 'product');
            $this->product = $sheet->product_id;
            $this->fillFrom($sheet, true);

            return;
        }

        $this->authorize('costing.manage');
        $p = Product::findOrFail($this->product);
        $this->basis = $costing->bases($p)[0];
        $this->loadMaterials($costing);
        $this->overheads = $costing->defaultOverheads($this->basis);

        if ($this->from && ($old = CostSheet::with('items')->where('product_id', $p->id)->find($this->from))) {
            $this->fillFrom($old, false);
            $this->loadMaterials($costing);   // fresh costs, old overheads and margins
        }
        $this->suggest($costing);
    }

    private function fillFrom(CostSheet $s, bool $withMaterials): void
    {
        $this->basis = $s->basis;
        $this->bom_id = $s->bom_id;
        if ($withMaterials) {
            $this->materials = $s->items->where('kind', 'material')->map(fn ($i) => ['product_id' => $i->product_id, 'description' => $i->description,
                'quantity' => (float) $i->quantity, 'wastage_percent' => (float) $i->wastage_percent, 'unit_cost' => (float) $i->unit_cost, 'amount' => (float) $i->amount])->values()->all();
            $this->dealer_price = (string) (float) $s->dealer_price;
            $this->retailer_price = (string) (float) $s->retailer_price;
            $this->mrp = (string) (float) $s->mrp;
            $this->manualPrices = true;
            $this->effective_from = $s->effective_from->toDateString();
            $this->first_supply_date = (string) $s->first_supply_date?->toDateString();
            $this->notes = (string) $s->notes;
        }
        $this->overheads = $s->items->where('kind', 'overhead')->map(fn ($i) => ['cost_head_id' => $i->cost_head_id, 'description' => $i->description,
            'basis' => $i->basis, 'rate' => (float) $i->rate])->values()->all();
        $this->company_margin = (string) (float) $s->company_margin;
        $this->dealer_margin = (string) (float) $s->dealer_margin;
        $this->retailer_margin = (string) (float) $s->retailer_margin;
    }

    private function editable(): bool
    {
        return ! $this->sheet || $this->sheet->editable();
    }

    private function loadMaterials(CostingService $costing): void
    {
        $m = $costing->materials(Product::findOrFail($this->product), $this->basis);
        $this->bom_id = $m['bom_id'];
        $this->materials = $m['lines'];
    }

    public function updatedBasis(): void
    {
        $costing = app(CostingService::class);
        abort_unless($this->editable(), 403);
        abort_unless(in_array($this->basis, $costing->bases(Product::findOrFail($this->product)), true), 422);
        $this->loadMaterials($costing);
        $this->overheads = $costing->defaultOverheads($this->basis);
        $this->suggest($costing);
    }

    public function updated(string $name): void
    {
        $costing = app(CostingService::class);
        if (in_array($name, ['dealer_price', 'retailer_price', 'mrp'], true)) {
            $this->manualPrices = true;

            return;
        }
        if (str_starts_with($name, 'materials.')) {
            foreach ($this->materials as $i => $l) {
                $this->materials[$i]['amount'] = round((float) $l['quantity'] * (float) $l['unit_cost'], 4);
            }
        }
        if (str_starts_with($name, 'materials.') || str_starts_with($name, 'overheads.') || str_ends_with($name, '_margin')) {
            $this->manualPrices || $this->suggest($costing);
        }
    }

    /** Put the suggested prices in the price boxes. */
    public function suggest(CostingService $costing): void
    {
        $c = $this->computed($costing);
        $this->dealer_price = (string) $c['dealer_price'];
        $this->retailer_price = (string) $c['retailer_price'];
        $this->mrp = (string) $c['mrp'];
        $this->manualPrices = false;
    }

    public function addOverhead(CostingService $costing): void
    {
        abort_unless($this->editable(), 403);
        $head = $this->addHead ? CostHead::find($this->addHead) : null;
        $this->overheads[] = $head
            ? ['cost_head_id' => $head->id, 'description' => $head->name, 'basis' => $head->basis, 'rate' => (float) $head->default_value]
            : ['cost_head_id' => null, 'description' => '', 'basis' => 'amount', 'rate' => 0];
        $this->addHead = null;
        $this->manualPrices || $this->suggest($costing);
    }

    public function removeOverhead(int $i, CostingService $costing): void
    {
        abort_unless($this->editable(), 403);
        unset($this->overheads[$i]);
        $this->overheads = array_values($this->overheads);
        $this->manualPrices || $this->suggest($costing);
    }

    private function computed(CostingService $costing): array
    {
        return $costing->compute($this->materials, $this->overheads, (float) $this->company_margin, (float) $this->dealer_margin, (float) $this->retailer_margin);
    }

    public function save(CostingService $costing)
    {
        $this->authorize('costing.manage');
        $this->validate([
            'basis' => 'required|in:'.implode(',', array_keys(CostSheet::BASES)),
            'materials' => 'array|min:1',
            'materials.*.quantity' => 'required|numeric|min:0',
            'materials.*.unit_cost' => 'required|numeric|min:0',
            'overheads.*.description' => 'required|string|max:255',
            'overheads.*.basis' => 'required|in:percent,amount',
            'overheads.*.rate' => 'required|numeric|min:0',
            'company_margin' => 'required|numeric|min:0|max:500',
            'dealer_margin' => 'required|numeric|min:0|max:500',
            'retailer_margin' => 'required|numeric|min:0|max:500',
            'dealer_price' => 'required|numeric|min:0',
            'retailer_price' => 'required|numeric|min:0',
            'mrp' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'first_supply_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ], [], ['materials' => __('materials'), 'overheads.*.description' => __('cost head')]);

        if ((float) $this->dealer_price > (float) $this->retailer_price || ((float) $this->mrp > 0 && (float) $this->retailer_price > (float) $this->mrp)) {
            $this->addError('retailer_price', __('Prices must go up: dealer price ≤ retailer price ≤ MRP.'));

            return null;
        }

        try {
            $sheet = $costing->save(Product::findOrFail($this->product), [
                'basis' => $this->basis, 'bom_id' => $this->bom_id, 'materials' => $this->materials, 'overheads' => $this->overheads,
                'company_margin' => (float) $this->company_margin, 'dealer_margin' => (float) $this->dealer_margin, 'retailer_margin' => (float) $this->retailer_margin,
                'dealer_price' => (float) $this->dealer_price, 'retailer_price' => (float) $this->retailer_price, 'mrp' => (float) $this->mrp,
                'effective_from' => $this->effective_from, 'first_supply_date' => $this->first_supply_date, 'notes' => $this->notes,
            ], $this->sheet);
        } catch (\RuntimeException $e) {
            $this->addError('materials', $e->getMessage());

            return null;
        }

        session()->flash('saved', __('Saved as draft.'));

        return $this->redirectRoute('costing.sheets.show', $sheet);
    }

    public function approve(CostingService $costing)
    {
        $this->authorize('costing.approve');
        try {
            $costing->approve($this->sheet);
        } catch (\RuntimeException $e) {
            $this->addError('approve', $e->getMessage());

            return null;
        }
        session()->flash('saved', $this->sheet->fresh()->status === 'applied' ? __('Approved. The new prices are in use.') : __('Approved. The prices change on :d.', ['d' => $this->sheet->effective_from->format('d M Y')]));

        return $this->redirectRoute('costing.sheets.show', $this->sheet);
    }

    public function reject(CostingService $costing)
    {
        $this->authorize('costing.approve');
        $costing->reject($this->sheet);

        return $this->redirectRoute('costing.sheets.show', $this->sheet);
    }

    public function delete()
    {
        $this->authorize('costing.manage');
        abort_unless($this->sheet?->status === 'draft', 403);
        $this->sheet->delete();

        return $this->redirectRoute('costing.index');
    }

    public function render(CostingService $costing)
    {
        $product = Product::with('unit', 'brand')->findOrFail($this->product);
        $sheet = $this->sheet?->fresh(['creator', 'approver']);

        return view('livewire.costing.sheet', [
            'p' => $product,
            'editable' => $this->editable(),
            'bases' => $this->editable() ? $costing->bases($product) : [$this->basis],
            'c' => $this->computed($costing),
            'heads' => CostHead::where('active', true)->orderBy('sort')->get(),
            'sheet' => $sheet,
            'actual' => $sheet ? $costing->actualCost($sheet) : null,
            'history' => CostSheet::where('product_id', $product->id)->orderByDesc('version')->limit(10)->get(),
            'current' => CostSheet::where('product_id', $product->id)->where('status', 'applied')->first(),
        ]);
    }
}
