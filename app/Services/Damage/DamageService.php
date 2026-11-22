<?php

namespace App\Services\Damage;

use App\Models\Batch;
use App\Models\Damage\DamageAction;
use App\Models\Damage\DamageRecord;
use App\Models\Damage\RepairJob;
use App\Models\Warehouse;
use App\Services\Accounts\AutoPosting;
use App\Services\InventoryService;
use App\Services\NumberGeneratorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Damage and repair.
 *
 * reported (stock still in its warehouse) → approved: moved to the owner's
 * damage store, batch number and cost unchanged → decided, in parts:
 *  - repair / reprocess: a repair job; when done the good units go back to a
 *    sellable warehouse (same batch number, cost + repair cost), the rest is
 *    scrapped;
 *  - sell at a discount: moved to a sellable warehouse as batch "…-D" at the
 *    recovery price, the difference written off;
 *  - destroy: taken out of stock and written off.
 * Damaged returns and QC rejects come in already approved.
 */
class DamageService
{
    public function __construct(
        private InventoryService $inventory,
        private NumberGeneratorService $numbers,
        private AutoPosting $posting,
    ) {}

    /** The damage store of the warehouse's owner (its factory's first, if any). */
    public static function damageStoreFor(Warehouse $warehouse): ?Warehouse
    {
        if ($warehouse->purpose === 'damage') {
            return $warehouse;
        }

        $q = fn () => Warehouse::withoutGlobalScope('tenant')->where('purpose', 'damage')->where('active', true)
            ->where(fn ($w) => $warehouse->dealer_id ? $w->where('dealer_id', $warehouse->dealer_id) : $w->whereNull('dealer_id'));

        return ($warehouse->factory_id ? $q()->where('factory_id', $warehouse->factory_id)->orderBy('id')->first() : null)
            ?? $q()->orderBy('id')->first();
    }

    /** Report damaged stock found in a warehouse or in transport. It stays where it is until approved. */
    public function report(int $batchId, float $quantity, string $source, string $reason, string $date): DamageRecord
    {
        $batch = Batch::with('warehouse')->findOrFail($batchId);
        if ($quantity <= 0 || bccomp((string) $batch->quantity_available, (string) $quantity, 3) < 0) {
            throw new \RuntimeException(__('Not enough left in batch :b.', ['b' => $batch->batch_no]));
        }
        if (! self::damageStoreFor($batch->warehouse)) {
            throw new \RuntimeException(__('Create a damage store first (Inventory → Warehouses, purpose "Damage store").'));
        }

        return DamageRecord::create([
            'number' => $this->numbers->generate('damage_record'),
            'source' => $source,
            'damage_date' => $date,
            'warehouse_id' => $batch->warehouse_id,
            'found_warehouse_id' => $batch->warehouse_id,
            'product_id' => $batch->product_id,
            'source_batch_id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'quantity' => $quantity,
            'quantity_open' => $quantity,
            'unit_cost' => $batch->cost_price,
            'reason' => $reason,
            'status' => 'reported',
            'reported_by' => Auth::id(),
        ]);
    }

    /** Approve: the stock moves into the damage store. */
    public function approve(DamageRecord $record): void
    {
        if ($record->status !== 'reported') {
            throw new \RuntimeException(__('This record is not waiting for approval.'));
        }

        DB::transaction(function () use ($record) {
            $from = Warehouse::withoutGlobalScope('tenant')->findOrFail($record->found_warehouse_id);
            $store = self::damageStoreFor($from) ?? throw new \RuntimeException(__('Create a damage store first (Inventory → Warehouses, purpose "Damage store").'));

            $batch = $store->id === $from->id
                ? Batch::findOrFail($record->source_batch_id)
                : $this->inventory->moveBatch($record->source_batch_id, $store->id, (float) $record->quantity, $record);

            $record->update([
                'status' => 'approved',
                'warehouse_id' => $store->id,
                'batch_id' => $batch->id,
                'unit_cost' => $batch->cost_price,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });
    }

    public function reject(DamageRecord $record, ?string $note): void
    {
        if ($record->status !== 'reported') {
            throw new \RuntimeException(__('This record is not waiting for approval.'));
        }
        $record->update(['status' => 'rejected', 'quantity_open' => 0, 'decision_note' => $note, 'approved_by' => Auth::id(), 'approved_at' => now()]);
    }

    /** Goods already in a damage store (a damaged return, QC rejects): registered as approved. */
    public function registerInStore(Batch $batch, float $quantity, string $source, string $reason, Model $reference): DamageRecord
    {
        return DamageRecord::create([
            'number' => $this->numbers->generate('damage_record'),
            'source' => $source,
            'damage_date' => today(),
            'warehouse_id' => $batch->warehouse_id,
            'found_warehouse_id' => $batch->warehouse_id,
            'product_id' => $batch->product_id,
            'batch_id' => $batch->id,
            'source_batch_id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'quantity' => $quantity,
            'quantity_open' => $quantity,
            'unit_cost' => $batch->cost_price,
            'reason' => $reason,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'status' => 'approved',
            'reported_by' => Auth::id(),
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Decide what happens to (part of) the damaged quantity.
     *
     * @param  array{target_warehouse_id?: ?int, recovery_price?: ?float, mode?: string, vendor?: ?string, expected_date?: ?string, estimated_cost?: ?float, notes?: ?string}  $opts
     */
    public function decide(DamageRecord $record, string $action, float $quantity, array $opts = []): DamageAction
    {
        if ($record->status !== 'approved') {
            throw new \RuntimeException(__('Only approved damage in the damage store can be decided.'));
        }
        if (! array_key_exists($action, DamageAction::ACTIONS)) {
            throw new \RuntimeException(__('Unknown decision.'));
        }
        $quantity = round($quantity, 3);
        if ($quantity <= 0 || bccomp((string) $record->quantity_open, (string) $quantity, 3) < 0) {
            throw new \RuntimeException(__('Only :n is still to be decided.', ['n' => (float) $record->quantity_open]));
        }

        return DB::transaction(function () use ($record, $action, $quantity, $opts) {
            $unit = (float) $record->unit_cost;
            $act = DamageAction::create([
                'damage_record_id' => $record->id,
                'action' => $action,
                'quantity' => $quantity,
                'notes' => $opts['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            switch ($action) {
                case 'destroy':
                    $this->inventory->takeFromBatch($record->batch_id, $quantity, $act);
                    $act->update(['written_off' => round($quantity * $unit, 2)]);
                    $this->posting->damageWriteOff($act, $record);
                    break;

                case 'discount_sale':
                    $target = $this->sellableWarehouse($record, $opts['target_warehouse_id'] ?? null);
                    $price = max(0, (float) ($opts['recovery_price'] ?? 0));
                    $newCost = min($unit, $price);
                    $source = $this->inventory->takeFromBatch($record->batch_id, $quantity, $act);
                    $batch = $this->inventory->receiveStock(
                        productId: $record->product_id,
                        warehouseId: $target->id,
                        batchNo: $record->batch_no.'-D',
                        expiryDate: $source->expiry_date ? \Carbon\Carbon::parse($source->expiry_date)->toDateString() : null,
                        costPrice: $newCost,
                        quantity: $quantity,
                        reference: $act,
                    );
                    $act->update([
                        'recovery_price' => $price,
                        'target_warehouse_id' => $target->id,
                        'output_batch_id' => $batch->id,
                        'written_off' => round($quantity * ($unit - $newCost), 2),
                    ]);
                    $this->posting->damageWriteOff($act, $record);
                    break;

                default:   // repair, reprocess
                    $job = RepairJob::create([
                        'number' => $this->numbers->generate('repair_job'),
                        'damage_record_id' => $record->id,
                        'warehouse_id' => $record->warehouse_id,
                        'product_id' => $record->product_id,
                        'batch_id' => $record->batch_id,
                        'quantity' => $quantity,
                        'kind' => $action,
                        'mode' => ($opts['mode'] ?? 'in_house') === 'outsourced' ? 'outsourced' : 'in_house',
                        'vendor' => $opts['vendor'] ?? null,
                        'sent_date' => today(),
                        'expected_date' => $opts['expected_date'] ?? null,
                        'estimated_cost' => (float) ($opts['estimated_cost'] ?? 0),
                        'return_warehouse_id' => $opts['target_warehouse_id'] ?? null,
                        'notes' => $opts['notes'] ?? null,
                        'created_by' => Auth::id(),
                    ]);
                    $act->update(['repair_job_id' => $job->id]);
            }

            $open = round((float) $record->quantity_open - $quantity, 3);
            $record->update(['quantity_open' => $open, 'status' => $open <= 0 && ! $this->hasOpenRepairs($record) ? 'closed' : 'approved']);

            return $act;
        });
    }

    /**
     * Repair done: repaired units go to a sellable warehouse with the same
     * batch number at (their value + repair cost) per unit; the rest is
     * scrapped and written off.
     */
    public function completeRepair(RepairJob $job, float $repaired, float $cost, ?int $paidFromAccountId, ?int $returnWarehouseId): void
    {
        if ($job->status !== 'open') {
            throw new \RuntimeException(__('This repair job is not open.'));
        }
        $repaired = round($repaired, 3);
        if ($repaired < 0 || $repaired > (float) $job->quantity) {
            throw new \RuntimeException(__('Repaired quantity must be between 0 and :n.', ['n' => (float) $job->quantity]));
        }
        if ($cost < 0) {
            throw new \RuntimeException(__('Amounts cannot be negative.'));
        }
        if ($cost > 0 && ! $paidFromAccountId) {
            throw new \RuntimeException(__('Choose the cash or bank account the repair was paid from.'));
        }

        DB::transaction(function () use ($job, $repaired, $cost, $paidFromAccountId, $returnWarehouseId) {
            $record = $job->record;
            $unit = (float) ($record?->unit_cost ?? Batch::find($job->batch_id)?->cost_price ?? 0);
            $scrapped = round((float) $job->quantity - $repaired, 3);

            $source = $this->inventory->takeFromBatch($job->batch_id, (float) $job->quantity, $job);
            $batch = null;
            if ($repaired > 0) {
                $target = $this->sellableWarehouse($record ?? $job, $returnWarehouseId ?? $job->return_warehouse_id);
                $batch = $this->inventory->receiveStock(
                    productId: $job->product_id,
                    warehouseId: $target->id,
                    batchNo: preg_replace('/-REJ$/', '', $source->batch_no),
                    expiryDate: $source->expiry_date ? \Carbon\Carbon::parse($source->expiry_date)->toDateString() : null,
                    costPrice: round(($repaired * $unit + $cost) / $repaired, 4),
                    quantity: $repaired,
                    reference: $job,
                );
                $returnWarehouseId = $target->id;
            }

            $job->update([
                'status' => 'completed',
                'completed_date' => today(),
                'repaired_quantity' => $repaired,
                'scrapped_quantity' => $scrapped,
                'cost' => $cost,
                'paid_from_account_id' => $cost > 0 ? $paidFromAccountId : null,
                'return_warehouse_id' => $returnWarehouseId,
                'output_batch_id' => $batch?->id,
            ]);

            // With nothing repaired, the repair cost is lost too.
            $this->posting->repairCompleted($job->fresh(), round($scrapped * $unit, 2), $repaired > 0);

            if ($record) {
                $record->update(['status' => (float) $record->quantity_open <= 0 && ! $this->hasOpenRepairs($record) ? 'closed' : 'approved']);
            }
        });
    }

    /** A repair that will not happen: its quantity is open for another decision again. */
    public function cancelRepair(RepairJob $job): void
    {
        if ($job->status !== 'open') {
            throw new \RuntimeException(__('This repair job is not open.'));
        }
        DB::transaction(function () use ($job) {
            $job->update(['status' => 'cancelled']);
            if ($record = $job->record) {
                $record->update(['quantity_open' => round((float) $record->quantity_open + (float) $job->quantity, 3), 'status' => 'approved']);
            }
        });
    }

    private function hasOpenRepairs(DamageRecord $record): bool
    {
        return RepairJob::where('damage_record_id', $record->id)->where('status', 'open')->exists();
    }

    /** Where good or discounted stock goes back to: the chosen warehouse, or the owner's first general store. */
    private function sellableWarehouse(Model $record, ?int $id): Warehouse
    {
        $store = Warehouse::withoutGlobalScope('tenant')->findOrFail($record->warehouse_id);
        $target = $id ? Warehouse::withoutGlobalScope('tenant')->find($id) : Warehouse::withoutGlobalScope('tenant')
            ->where(fn ($w) => $store->dealer_id ? $w->where('dealer_id', $store->dealer_id) : $w->whereNull('dealer_id'))
            ->where('active', true)->whereNotIn('purpose', ['damage', 'raw_material'])->orderByRaw("CASE WHEN purpose = 'finished_goods' THEN 0 ELSE 1 END")->orderBy('id')->first();

        if (! $target || (int) $target->dealer_id !== (int) $store->dealer_id || $target->purpose === 'damage') {
            throw new \RuntimeException(__('Choose one of your own warehouses (not a damage store) for the goods to go to.'));
        }

        return $target;
    }
}
