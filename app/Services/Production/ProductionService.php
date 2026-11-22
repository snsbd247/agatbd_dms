<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\Factory;
use App\Models\Product;
use App\Models\Production\Bom;
use App\Models\Production\ProductionIssue;
use App\Models\Production\ProductionOrder;
use App\Models\Warehouse;
use App\Services\Accounts\AutoPosting;
use App\Services\InventoryService;
use App\Services\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Batch-wise production.
 *
 * planned → in_progress (materials issued, batch by batch, FIFO by default)
 *         → qc (quality checks) → completed: good output goes into the
 * finished-goods store as one new batch (manufacture and expiry dates, cost =
 * materials + labour + overhead ÷ good quantity); rejects go to the damage
 * store. Every issued material batch is recorded, so a finished batch can be
 * traced back to its raw materials and forward to the customers who got it.
 */
class ProductionService
{
    public function __construct(
        private InventoryService $inventory,
        private NumberGeneratorService $numbers,
        private AutoPosting $posting,
    ) {}

    /** The factory's store for a purpose (raw material, finished goods, damage). */
    public static function store(Factory|int $factory, string $purpose): ?Warehouse
    {
        $id = $factory instanceof Factory ? $factory->id : $factory;

        return Warehouse::where('factory_id', $id)->where('purpose', $purpose)->where('active', true)->orderBy('id')->first();
    }

    public function nextBatchNo(Product $product, string $date): string
    {
        $stem = strtoupper($product->sku).'-'.Carbon::parse($date)->format('ymd');
        $n = ProductionOrder::where('batch_no', 'like', $stem.'-%')->count() + 1;

        return $stem.'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{factory_id: int, product_id: int, bom_id: int, planned_quantity: float, mfg_date: string, machine_id?: ?int, batch_no?: ?string, raw_warehouse_id?: ?int, output_warehouse_id?: ?int, damage_warehouse_id?: ?int, notes?: ?string}  $data
     */
    public function create(array $data): ProductionOrder
    {
        $product = Product::findOrFail($data['product_id']);
        $bom = Bom::with('items')->findOrFail($data['bom_id']);

        if ($bom->product_id !== $product->id) {
            throw new \RuntimeException(__('This formula is for another product.'));
        }
        if ($bom->items->isEmpty()) {
            throw new \RuntimeException(__('The formula has no materials.'));
        }

        $raw = $data['raw_warehouse_id'] ?? self::store($data['factory_id'], 'raw_material')?->id;
        $out = $data['output_warehouse_id'] ?? self::store($data['factory_id'], 'finished_goods')?->id;
        if (! $raw || ! $out) {
            throw new \RuntimeException(__('Link a raw material store and a finished goods store to this factory first (Inventory → Warehouses).'));
        }

        $mfg = Carbon::parse($data['mfg_date'])->toDateString();

        return ProductionOrder::create([
            'number' => $this->numbers->generate('production_order'),
            'factory_id' => $data['factory_id'],
            'product_id' => $product->id,
            'bom_id' => $bom->id,
            'machine_id' => $data['machine_id'] ?? null,
            'planned_quantity' => $data['planned_quantity'],
            'batch_no' => ($data['batch_no'] ?? '') ?: $this->nextBatchNo($product, $mfg),
            'mfg_date' => $mfg,
            'expiry_date' => $product->shelf_life_days ? Carbon::parse($mfg)->addDays($product->shelf_life_days)->toDateString() : null,
            'raw_warehouse_id' => $raw,
            'output_warehouse_id' => $out,
            'damage_warehouse_id' => $data['damage_warehouse_id'] ?? self::store($data['factory_id'], 'damage')?->id,
            'status' => 'planned',
            'standard_cost' => $bom->standardCost((float) $data['planned_quantity']),
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Per material: required (formula × planned, with wastage), issued so far,
     * still to issue and available in the raw-material store.
     *
     * @return array<int, array{product: Product, required: float, issued: float, remaining: float, available: float}>
     */
    public function materials(ProductionOrder $order): array
    {
        $order->loadMissing('bom.items.product');
        $issued = ProductionIssue::where('production_order_id', $order->id)->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as q')->pluck('q', 'product_id');
        $available = Batch::where('warehouse_id', $order->raw_warehouse_id)->where('quantity_available', '>', 0)
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity_available) as q')->pluck('q', 'product_id');

        $rows = [];
        foreach ($order->bom->requirements((float) $order->planned_quantity) as $pid => $required) {
            $done = (float) ($issued[$pid] ?? 0);
            $rows[$pid] = [
                'product' => $order->bom->items->firstWhere('product_id', $pid)->product,
                'required' => $required,
                'issued' => round($done, 3),
                'remaining' => round(max(0, $required - $done), 3),
                'available' => round((float) ($available[$pid] ?? 0), 3),
            ];
        }

        return $rows;
    }

    /**
     * Issue materials from the raw-material store. Without $quantities, what
     * is still required is issued; quantities are taken FIFO across batches
     * (oldest first, earliest expiry first).
     *
     * @param  array<int, float>|null  $quantities  product id => quantity
     */
    public function issue(ProductionOrder $order, ?array $quantities = null): int
    {
        if (! in_array($order->status, ['planned', 'in_progress', 'qc'], true)) {
            throw new \RuntimeException(__('Materials can be issued only while the batch is being made.'));
        }

        $quantities ??= collect($this->materials($order))->map(fn ($m) => $m['remaining'])->all();
        $quantities = array_filter(array_map(fn ($q) => round((float) $q, 3), $quantities), fn ($q) => $q > 0);
        if ($quantities === []) {
            throw new \RuntimeException(__('Nothing left to issue.'));
        }

        return DB::transaction(function () use ($order, $quantities) {
            $issueNo = (int) ProductionIssue::where('production_order_id', $order->id)->max('issue_no') + 1;
            $value = 0.0;

            foreach ($quantities as $productId => $qty) {
                $batches = Batch::where('warehouse_id', $order->raw_warehouse_id)->where('product_id', $productId)
                    ->where('quantity_available', '>', 0)
                    ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')->orderBy('expiry_date')->orderBy('id')
                    ->get();

                if ((float) $batches->sum('quantity_available') + 0.0005 < $qty) {
                    throw new \RuntimeException(__('Not enough :p in the raw material store (need :n).', ['p' => Product::find($productId)?->name, 'n' => $qty]));
                }

                $left = $qty;
                foreach ($batches as $batch) {
                    if ($left <= 0.0005) {
                        break;
                    }
                    $take = round(min($left, (float) $batch->quantity_available), 3);
                    $this->inventory->takeFromBatch($batch->id, $take, $order);
                    ProductionIssue::create([
                        'production_order_id' => $order->id,
                        'issue_no' => $issueNo,
                        'product_id' => $productId,
                        'batch_id' => $batch->id,
                        'quantity' => $take,
                        'unit_cost' => $batch->cost_price,
                        'issued_by' => Auth::id(),
                    ]);
                    $value += $take * (float) $batch->cost_price;
                    $left -= $take;
                }
            }

            $order->update([
                'status' => $order->status === 'planned' ? 'in_progress' : $order->status,
                'started_at' => $order->started_at ?? now(),
                'material_cost' => round((float) ProductionIssue::where('production_order_id', $order->id)->get()->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost), 2),
            ]);

            $this->posting->productionIssue($order, $issueNo, round($value, 2));

            return $issueNo;
        });
    }

    public function qc(ProductionOrder $order, string $result, ?string $parameters, ?string $notes): void
    {
        if (! in_array($order->status, ['in_progress', 'qc'], true)) {
            throw new \RuntimeException(__('Quality checks are recorded after materials are issued.'));
        }

        DB::transaction(function () use ($order, $result, $parameters, $notes) {
            $order->qcChecks()->create([
                'result' => $result,
                'parameters' => $parameters,
                'notes' => $notes,
                'checked_by' => Auth::id(),
                'checked_at' => now(),
            ]);
            $order->update(['status' => 'qc']);
        });
    }

    public function complete(ProductionOrder $order, float $good, float $rejected, float $labour, float $overhead): void
    {
        if (! in_array($order->status, ['in_progress', 'qc'], true)) {
            throw new \RuntimeException(__('Only a batch in production can be completed.'));
        }
        if ($good > 0 && $order->qcChecks()->value('result') !== 'pass') {
            throw new \RuntimeException(__('The latest quality check must pass before good output is put into stock.'));
        }
        if ($good < 0 || $rejected < 0 || $labour < 0 || $overhead < 0) {
            throw new \RuntimeException(__('Amounts cannot be negative.'));
        }

        DB::transaction(function () use ($order, $good, $rejected, $labour, $overhead) {
            $material = round((float) ProductionIssue::where('production_order_id', $order->id)->get()->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost), 2);
            $total = round($material + $labour + $overhead, 2);
            $unit = $good > 0 ? round($total / $good, 4) : 0.0;

            $batch = null;
            if ($good > 0) {
                $batch = $this->inventory->receiveStock(
                    productId: $order->product_id,
                    warehouseId: $order->output_warehouse_id,
                    batchNo: $order->batch_no,
                    expiryDate: $order->expiry_date?->toDateString(),
                    costPrice: $unit,
                    quantity: $good,
                    reference: $order,
                );
            }
            if ($rejected > 0 && $order->damage_warehouse_id) {
                // Rejects keep the batch number for tracing; their cost is already in the good units.
                $this->inventory->receiveStock(
                    productId: $order->product_id,
                    warehouseId: $order->damage_warehouse_id,
                    batchNo: $order->batch_no.'-REJ',
                    expiryDate: $order->expiry_date?->toDateString(),
                    costPrice: 0,
                    quantity: $rejected,
                    reference: $order,
                );
            }

            $order->update([
                'status' => 'completed',
                'finished_at' => now(),
                'good_quantity' => $good,
                'rejected_quantity' => $rejected,
                'material_cost' => $material,
                'labour_cost' => $labour,
                'overhead_cost' => $overhead,
                'total_cost' => $total,
                'unit_cost' => $unit,
                'output_batch_id' => $batch?->id,
                'completed_by' => Auth::id(),
            ]);

            $this->posting->productionComplete($order->fresh());
        });
    }

    public function cancel(ProductionOrder $order): void
    {
        if ($order->status !== 'planned' || $order->issues()->exists()) {
            throw new \RuntimeException(__('Only a planned batch with no materials issued can be cancelled. Complete it instead (good quantity 0 writes the materials off).'));
        }
        $order->update(['status' => 'cancelled']);
    }
}
