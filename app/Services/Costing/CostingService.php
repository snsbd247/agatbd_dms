<?php

namespace App\Services\Costing;

use App\Models\Costing\CostHead;
use App\Models\Costing\CostSheet;
use App\Models\Import\ImportItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Production\Bom;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cost sheets (price declaration).
 *
 *   base cost      buying cost, import landed cost, or formula / kit materials per unit
 * + overheads      cost heads: % of the base cost or taka per unit
 * = total cost
 *   dealer price   = total cost × (1 + company margin %)
 *   retailer price = dealer price × (1 + dealer margin %)
 *   MRP            = retailer price × (1 + retailer margin %)
 *
 * Prices are rounded to whole taka and may be typed over. An approved sheet
 * becomes the product's prices on its effective date (applyDue runs daily).
 */
class CostingService
{
    public function __construct(private NumberGeneratorService $numbers) {}

    /** The ways this product's base cost can be worked out, best first. */
    public function bases(Product $product): array
    {
        $bases = [];
        if ($this->bom($product, ['kit', 'repack'])) {
            $bases[] = 'kit';
        }
        if ($this->bom($product, ['production'])) {
            $bases[] = 'formula';
        }
        if ($this->lastLanded($product->id) !== null) {
            $bases[] = 'import';
        }
        $bases[] = 'purchase';

        return array_values(array_unique($bases));
    }

    public function bom(Product $product, array $kinds): ?Bom
    {
        return Bom::with('items.product.unit')->where('product_id', $product->id)->whereIn('kind', $kinds)
            ->orderByDesc('is_default')->orderByDesc('version')->first();
    }

    /**
     * Material lines for a basis, per one unit of the product.
     *
     * @return array{bom_id: ?int, lines: array<int, array{product_id: ?int, description: string, quantity: float, wastage_percent: float, unit_cost: float, amount: float}>}
     */
    public function materials(Product $product, string $basis): array
    {
        if (in_array($basis, ['formula', 'kit'], true)) {
            $bom = $this->bom($product, $basis === 'kit' ? ['kit', 'repack'] : ['production']);
            if (! $bom) {
                return ['bom_id' => null, 'lines' => []];
            }
            $per = (float) $bom->output_quantity > 0 ? 1 / (float) $bom->output_quantity : 0;
            $lines = [];
            foreach ($bom->items as $item) {
                $waste = (float) $item->wastage_percent;
                $qty = round((float) $item->quantity * $per * (1 + $waste / 100), 4);
                $unit = Bom::currentCost($item->product_id);
                $lines[] = ['product_id' => $item->product_id, 'description' => $item->product?->name ?? '—', 'quantity' => $qty,
                    'wastage_percent' => $waste, 'unit_cost' => round($unit, 4), 'amount' => round($qty * $unit, 4)];
            }

            return ['bom_id' => $bom->id, 'lines' => $lines];
        }

        $unit = $basis === 'import' ? ($this->lastLanded($product->id) ?? 0.0) : Bom::currentCost($product->id);
        $label = $basis === 'import' ? __('Landed cost of the last import') : __('Buying cost');

        return ['bom_id' => null, 'lines' => [['product_id' => $product->id, 'description' => $label, 'quantity' => 1.0,
            'wastage_percent' => 0.0, 'unit_cost' => round($unit, 4), 'amount' => round($unit, 4)]]];
    }

    /** The cost heads that fit this basis, with their default rates. */
    public function defaultOverheads(string $basis): array
    {
        $kind = $basis === 'formula' ? 'manufacturing' : 'trading';

        return CostHead::where('active', true)->whereIn('applies_to', ['all', $kind])->orderBy('sort')->orderBy('id')->get()
            ->map(fn ($h) => ['cost_head_id' => $h->id, 'description' => $h->name, 'basis' => $h->basis, 'rate' => (float) $h->default_value])
            ->all();
    }

    public function lastLanded(int $productId): ?float
    {
        $v = ImportItem::where('product_id', $productId)->where('landed_unit_cost', '>', 0)
            ->whereHas('shipment', fn ($q) => $q->where('status', 'received'))
            ->latest('id')->value('landed_unit_cost');

        return $v === null ? null : (float) $v;
    }

    /**
     * Work out the totals and suggested prices.
     *
     * @return array{base: float, overheads: array, overhead: float, total: float, dealer_price: float, retailer_price: float, mrp: float}
     */
    public function compute(array $materials, array $overheads, float $companyMargin, float $dealerMargin, float $retailerMargin): array
    {
        $base = round(array_sum(array_map(fn ($l) => (float) $l['amount'], $materials)), 4);
        $over = 0.0;
        foreach ($overheads as $i => $o) {
            $amount = ($o['basis'] ?? 'percent') === 'amount' ? (float) $o['rate'] : $base * (float) $o['rate'] / 100;
            $overheads[$i]['amount'] = round($amount, 4);
            $over += $amount;
        }
        $total = round($base + $over, 4);
        $dealer = round($total * (1 + $companyMargin / 100));
        $retailer = round($dealer * (1 + $dealerMargin / 100));
        $mrp = round($retailer * (1 + $retailerMargin / 100));

        return ['base' => $base, 'overheads' => $overheads, 'overhead' => round($over, 4), 'total' => $total,
            'dealer_price' => (float) $dealer, 'retailer_price' => (float) $retailer, 'mrp' => (float) $mrp];
    }

    /**
     * Save a new draft, or replace a draft's contents.
     *
     * @param  array{basis: string, bom_id: ?int, materials: array, overheads: array, company_margin: float, dealer_margin: float, retailer_margin: float, dealer_price: float, retailer_price: float, mrp: float, effective_from: string, first_supply_date: ?string, notes: ?string}  $data
     */
    public function save(Product $product, array $data, ?CostSheet $sheet = null): CostSheet
    {
        if ($sheet && ! $sheet->editable()) {
            throw new \RuntimeException(__('Only a draft can be changed. Make a new version instead.'));
        }
        $c = $this->compute($data['materials'], $data['overheads'], (float) $data['company_margin'], (float) $data['dealer_margin'], (float) $data['retailer_margin']);
        if ($c['total'] <= 0) {
            throw new \RuntimeException(__('The cost is zero. Enter the buying cost or check the formula materials.'));
        }

        return DB::transaction(function () use ($product, $data, $sheet, $c) {
            $fields = [
                'basis' => $data['basis'],
                'bom_id' => $data['bom_id'] ?? null,
                'base_cost' => $c['base'],
                'overhead_cost' => $c['overhead'],
                'total_cost' => $c['total'],
                'company_margin' => $data['company_margin'],
                'dealer_margin' => $data['dealer_margin'],
                'retailer_margin' => $data['retailer_margin'],
                'dealer_price' => $data['dealer_price'],
                'retailer_price' => $data['retailer_price'],
                'mrp' => $data['mrp'],
                'vat_rate' => (float) ($product->vat_rate ?? 0),
                'effective_from' => $data['effective_from'],
                'first_supply_date' => $data['first_supply_date'] ?: null,
                'notes' => $data['notes'] ?: null,
            ];

            if ($sheet) {
                $sheet->update($fields);
                $sheet->items()->delete();
            } else {
                $sheet = CostSheet::create($fields + [
                    'number' => $this->numbers->generate('cost_sheet'),
                    'product_id' => $product->id,
                    'version' => (int) CostSheet::where('product_id', $product->id)->max('version') + 1,
                    'status' => 'draft',
                    'created_by' => Auth::id(),
                ]);
            }

            $sort = 0;
            foreach ($data['materials'] as $l) {
                $sheet->items()->create(['kind' => 'material', 'product_id' => $l['product_id'] ?? null, 'description' => $l['description'],
                    'quantity' => $l['quantity'], 'wastage_percent' => $l['wastage_percent'] ?? 0, 'unit_cost' => $l['unit_cost'],
                    'amount' => round((float) $l['quantity'] * (float) $l['unit_cost'], 4), 'sort' => $sort++]);
            }
            foreach ($c['overheads'] as $o) {
                $sheet->items()->create(['kind' => 'overhead', 'cost_head_id' => $o['cost_head_id'] ?? null, 'description' => $o['description'],
                    'basis' => $o['basis'], 'rate' => $o['rate'], 'amount' => $o['amount'], 'sort' => $sort++]);
            }

            return $sheet->fresh('items');
        });
    }

    public function approve(CostSheet $sheet): void
    {
        if ($sheet->status !== 'draft') {
            throw new \RuntimeException(__('Only a draft can be approved.'));
        }
        if ((float) $sheet->retailer_price <= 0) {
            throw new \RuntimeException(__('Set the prices before approving.'));
        }
        $sheet->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now()]);

        if ($sheet->effective_from->lte(today())) {
            $this->apply($sheet->fresh());
        }
    }

    public function reject(CostSheet $sheet): void
    {
        if (! in_array($sheet->status, ['draft', 'approved'], true)) {
            throw new \RuntimeException(__('This sheet is already in use or replaced.'));
        }
        $sheet->update(['status' => 'rejected']);
    }

    /** The sheet's prices become the product's prices; the sheet in use before is replaced. */
    public function apply(CostSheet $sheet): void
    {
        DB::transaction(function () use ($sheet) {
            CostSheet::where('product_id', $sheet->product_id)->where('status', 'applied')->update(['status' => 'superseded']);
            $sheet->update(['status' => 'applied', 'applied_at' => now()]);

            $product = $sheet->product;
            $product->update([
                'sale_price' => $sheet->retailer_price,
                'dealer_price' => $sheet->dealer_price,
                'mrp' => $sheet->mrp,
            ]);
            ProductPrice::create([
                'product_id' => $product->id,
                'purchase_price' => $product->purchase_price,
                'sale_price' => $sheet->retailer_price,
                'dealer_price' => $sheet->dealer_price,
                'mrp' => $sheet->mrp,
                'cost_sheet_id' => $sheet->id,
                'effective_from' => $sheet->effective_from->max(today()),
                'created_by' => $sheet->approved_by,
            ]);
        });
    }

    /** Approved sheets whose date has come. Returns how many were applied. */
    public function applyDue(): int
    {
        $n = 0;
        foreach (CostSheet::where('status', 'approved')->whereDate('effective_from', '<=', today())->orderBy('effective_from')->orderBy('id')->get() as $sheet) {
            $this->apply($sheet);
            $n++;
        }

        return $n;
    }

    /**
     * Declared base cost (materials or buying cost per unit) against what it
     * actually cost lately: production batches
     * (last 90 days), the last import, or the last purchase.
     *
     * @return array{actual: ?float, source: string}
     */
    public function actualCost(CostSheet $sheet): array
    {
        if ($sheet->basis === 'formula') {
            $orders = \App\Models\Production\ProductionOrder::where('product_id', $sheet->product_id)->where('status', 'completed')
                ->where('finished_at', '>=', now()->subDays(90))->where('good_quantity', '>', 0)->get();
            $qty = (float) $orders->sum('good_quantity');
            $cost = (float) $orders->sum('material_cost');

            return ['actual' => $qty > 0 ? round($cost / $qty, 4) : null, 'source' => __(':n batches, last 90 days', ['n' => $orders->count()])];
        }
        if ($sheet->basis === 'kit') {
            $orders = \App\Models\Kits\AssemblyOrder::where('product_id', $sheet->product_id)->where('assembly_date', '>=', today()->subDays(90))->get();
            $qty = (float) $orders->sum('quantity');

            return ['actual' => $qty > 0 ? round((float) $orders->sum('material_cost') / $qty, 4) : null,
                'source' => __(':n assemblies, last 90 days', ['n' => $orders->count()])];
        }
        if ($sheet->basis === 'import') {
            return ['actual' => $this->lastLanded($sheet->product_id), 'source' => __('Last import')];
        }

        return ['actual' => Bom::currentCost($sheet->product_id) ?: null, 'source' => __('Last purchase')];
    }
}
