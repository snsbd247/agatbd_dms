<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockLedger;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class InventoryService
{
    public function receiveStock(
        int $productId,
        int $warehouseId,
        string $batchNo,
        ?string $expiryDate,
        float $costPrice,
        float $quantity,
        Model $reference,
    ): Batch {
        return DB::transaction(function () use ($productId, $warehouseId, $batchNo, $expiryDate, $costPrice, $quantity, $reference) {
            $batch = Batch::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_no' => $batchNo,
                'expiry_date' => $expiryDate,
                'cost_price' => $costPrice,
                'quantity_received' => $quantity,
                'quantity_available' => $quantity,
            ]);

            $stock = Stock::firstOrCreate(
                ['warehouse_id' => $warehouseId, 'product_id' => $productId],
                ['quantity' => 0]
            );
            $stock = Stock::query()->whereKey($stock->id)->lockForUpdate()->first();
            $stock->increment('quantity', $quantity);

            StockLedger::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_id' => $batch->id,
                'type' => 'in',
                'quantity' => $quantity,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
                'balance_after' => $stock->quantity,
            ]);

            return $batch;
        });
    }

    /**
     * @return array<int, array{batch_id: int, quantity: float}>
     */
    public function consumeStock(int $productId, int $warehouseId, float $quantity, Model $reference): array
    {
        $beforeQuantity = null;
        $afterStock = null;

        $consumed = DB::transaction(function () use ($productId, $warehouseId, $quantity, $reference, &$beforeQuantity, &$afterStock) {
            $stock = Stock::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $stock || bccomp((string) $stock->quantity, (string) $quantity, 3) < 0) {
                throw new \RuntimeException('Insufficient stock to fulfill the requested quantity.');
            }

            $beforeQuantity = (float) $stock->quantity;

            $batches = Batch::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('quantity_available', '>', 0)
                ->oldest('created_at')
                ->lockForUpdate()
                ->get();

            $remaining = $quantity;
            $consumed = [];

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min((float) $batch->quantity_available, $remaining);
                $batch->decrement('quantity_available', $take);
                $remaining -= $take;
                $consumed[] = ['batch_id' => $batch->id, 'quantity' => $take];
            }

            $stock->decrement('quantity', $quantity);

            StockLedger::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_id' => $consumed[0]['batch_id'] ?? null,
                'type' => 'out',
                'quantity' => $quantity,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
                'balance_after' => $stock->quantity,
            ]);

            $afterStock = $stock;

            return $consumed;
        });

        if (
            $afterStock
            && (float) $afterStock->reorder_level > 0
            && $beforeQuantity > (float) $afterStock->reorder_level
            && (float) $afterStock->quantity <= (float) $afterStock->reorder_level
        ) {
            $recipients = User::role(['Dealer', 'Manager'])->get()->filter(fn (User $u) => $u->wantsNotification('low_stock_alert'));
            Notification::send($recipients, new LowStockAlert($afterStock->load(['product', 'warehouse'])));
        }

        return $consumed;
    }

    /**
     * Manual stock correction (damage, loss, count difference, found stock).
     *
     * A positive change adds to the chosen batch, or opens a new "ADJ-" batch
     * at the product's purchase price when no batch is chosen. A negative
     * change takes from the chosen batch, or FIFO across batches. Stock,
     * batches and the ledger move together; the ledger row has type
     * "adjustment" and a signed quantity.
     */
    public function adjustStock(
        int $warehouseId,
        int $productId,
        ?int $batchId,
        float $change,
        string $reason,
        ?int $userId,
        string $date,
    ): StockAdjustment {
        if (abs($change) < 0.0005) {
            throw new \RuntimeException('Quantity change cannot be zero.');
        }

        return DB::transaction(function () use ($warehouseId, $productId, $batchId, $change, $reason, $userId, $date) {
            $stock = Stock::firstOrCreate(
                ['warehouse_id' => $warehouseId, 'product_id' => $productId],
                ['quantity' => 0]
            );
            $stock = Stock::query()->whereKey($stock->id)->lockForUpdate()->first();

            $batch = null;

            if ($batchId) {
                $batch = Batch::query()
                    ->whereKey($batchId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                if (! $batch) {
                    throw new \RuntimeException('The selected batch does not belong to this product and warehouse.');
                }
            }

            if ($change > 0) {
                if ($batch) {
                    $batch->increment('quantity_available', $change);
                } else {
                    $batch = Batch::create([
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'batch_no' => 'ADJ-'.now()->format('YmdHis'),
                        'expiry_date' => null,
                        'cost_price' => (float) (Product::whereKey($productId)->value('purchase_price') ?? 0),
                        'quantity_received' => $change,
                        'quantity_available' => $change,
                    ]);
                }

                $stock->increment('quantity', $change);
            } else {
                $take = abs($change);

                if (bccomp((string) $stock->quantity, (string) $take, 3) < 0) {
                    throw new \RuntimeException('Cannot remove more than the current stock ('.(float) $stock->quantity.').');
                }

                if ($batch) {
                    if (bccomp((string) $batch->quantity_available, (string) $take, 3) < 0) {
                        throw new \RuntimeException('Cannot remove more than this batch has ('.(float) $batch->quantity_available.').');
                    }
                    $batch->decrement('quantity_available', $take);
                } else {
                    $remaining = $take;
                    $batches = Batch::query()
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->where('quantity_available', '>', 0)
                        ->oldest('created_at')
                        ->lockForUpdate()
                        ->get();

                    foreach ($batches as $b) {
                        if ($remaining <= 0) {
                            break;
                        }
                        $part = min((float) $b->quantity_available, $remaining);
                        $b->decrement('quantity_available', $part);
                        $remaining -= $part;
                        $batch ??= $b;
                    }
                }

                $stock->decrement('quantity', $take);
            }

            $adjustment = StockAdjustment::create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'batch_id' => $batch?->id,
                'quantity_change' => $change,
                'reason' => $reason,
                'adjusted_by' => $userId,
                'adjustment_date' => $date,
            ]);

            StockLedger::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_id' => $batch?->id,
                'type' => 'adjustment',
                'quantity' => $change,
                'reference_type' => $adjustment->getMorphClass(),
                'reference_id' => $adjustment->getKey(),
                'balance_after' => $stock->fresh()->quantity,
            ]);

            return $adjustment;
        });
    }

    public function returnToSupplier(int $batchId, float $quantity, Model $reference): void
    {
        DB::transaction(function () use ($batchId, $quantity, $reference) {
            $batch = Batch::query()->whereKey($batchId)->lockForUpdate()->first();

            if (! $batch || bccomp((string) $batch->quantity_available, (string) $quantity, 3) < 0) {
                throw new \RuntimeException('Insufficient batch quantity to return to supplier.');
            }

            $batch->decrement('quantity_available', $quantity);

            $stock = Stock::query()
                ->where('warehouse_id', $batch->warehouse_id)
                ->where('product_id', $batch->product_id)
                ->lockForUpdate()
                ->first();
            $stock->decrement('quantity', $quantity);

            StockLedger::create([
                'product_id' => $batch->product_id,
                'warehouse_id' => $batch->warehouse_id,
                'batch_id' => $batch->id,
                'type' => 'out',
                'quantity' => $quantity,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
                'balance_after' => $stock->quantity,
            ]);
        });
    }
}
