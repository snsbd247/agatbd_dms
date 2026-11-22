<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\Production\StockTransfer;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Moving stock between a business's own warehouses (factory store → depot,
 * depot → depot). Batches keep their number, expiry and cost, so tracing
 * works across warehouses. No accounting entry: the stock is still ours.
 */
class StockTransferService
{
    public function __construct(private InventoryService $inventory, private NumberGeneratorService $numbers) {}

    /** @param array<int, array{batch_id: int, quantity: float}> $lines */
    public function create(int $fromId, int $toId, string $date, array $lines, ?string $notes = null): StockTransfer
    {
        if ($fromId === $toId) {
            throw new \RuntimeException(__('Choose two different warehouses.'));
        }

        $from = Warehouse::find($fromId);
        $to = Warehouse::find($toId);
        if (! $from || ! $to || (int) $from->dealer_id !== (int) $to->dealer_id) {
            throw new \RuntimeException(__('Stock can be moved only between your own warehouses.'));
        }

        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['quantity'] ?? 0) > 0));
        if ($lines === []) {
            throw new \RuntimeException(__('Enter a quantity for at least one batch.'));
        }

        return DB::transaction(function () use ($from, $to, $date, $lines, $notes) {
            $transfer = StockTransfer::create([
                'number' => $this->numbers->generate('stock_transfer'),
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'transfer_date' => $date,
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                $batch = Batch::where('warehouse_id', $from->id)->findOrFail($line['batch_id']);
                $arrived = $this->inventory->moveBatch($batch->id, $to->id, (float) $line['quantity'], $transfer);
                $transfer->items()->create([
                    'product_id' => $batch->product_id,
                    'batch_id' => $batch->id,
                    'to_batch_id' => $arrived->id,
                    'quantity' => $line['quantity'],
                ]);
            }

            return $transfer;
        });
    }
}
