<?php

namespace App\Services\Kits;

use App\Models\Accounts\Account;
use App\Models\Batch;
use App\Models\Kits\AssemblyOrder;
use App\Models\Product;
use App\Models\Production\Bom;
use App\Models\Stock;
use App\Services\Accounts\LedgerService;
use App\Services\InventoryService;
use App\Services\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Kits and repacking in one step: the recipe's components leave the
 * warehouse (earliest expiry first) and the pack comes in as a new batch at
 * component cost plus any packing labour. The components' value simply
 * moves inside Inventory; only labour paid makes a voucher
 * (Dr Inventory / Cr cash-bank). The pack expires with its earliest component.
 */
class AssemblyService
{
    public function __construct(
        private InventoryService $inventory,
        private NumberGeneratorService $numbers,
        private LedgerService $ledger,
    ) {}

    public function assemble(Bom $bom, float $quantity, int $warehouseId, string $date, float $labour = 0, ?int $paidFromId = null, ?string $batchNo = null, ?string $notes = null): AssemblyOrder
    {
        if (! in_array($bom->kind, ['kit', 'repack'], true)) {
            throw new \RuntimeException(__('Choose a kit or repacking recipe.'));
        }
        $quantity = round($quantity, 3);
        if ($quantity <= 0) {
            throw new \RuntimeException(__('Enter how many to make.'));
        }
        if ($labour > 0 && ! Account::money()->whereKey($paidFromId)->exists()) {
            throw new \RuntimeException(__('Choose the cash or bank account the labour is paid from.'));
        }

        $bom->loadMissing('items.product', 'product');
        $need = $bom->requirements($quantity);
        foreach ($need as $productId => $qty) {
            $have = (float) Stock::where('product_id', $productId)->where('warehouse_id', $warehouseId)->value('quantity');
            if ($have + 0.0005 < $qty) {
                throw new \RuntimeException(__('Not enough :p (need :n, have :h).', ['p' => Product::find($productId)?->name, 'n' => $qty, 'h' => $have]));
            }
        }

        return DB::transaction(function () use ($bom, $quantity, $warehouseId, $date, $labour, $paidFromId, $batchNo, $notes, $need) {
            $product = $bom->product;
            $order = AssemblyOrder::create([
                'number' => $this->numbers->generate('assembly_order'),
                'kind' => $bom->kind,
                'bom_id' => $bom->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'assembly_date' => $date,
                'batch_no' => $batchNo ?: $this->nextBatchNo($product, $date, $bom->kind),
                'labour_cost' => round($labour, 2),
                'paid_from_account_id' => $labour > 0 ? $paidFromId : null,
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            $material = 0.0;
            $expiry = null;
            foreach ($need as $productId => $qty) {
                foreach ($this->inventory->consumeStock($productId, $warehouseId, $qty, $order) as $split) {
                    $batch = Batch::find($split['batch_id']);
                    $cost = (float) ($batch?->cost_price ?? 0);
                    $order->items()->create(['product_id' => $productId, 'batch_id' => $split['batch_id'], 'quantity' => $split['quantity'], 'unit_cost' => $cost]);
                    $material += (float) $split['quantity'] * $cost;
                    if ($batch?->expiry_date && (! $expiry || $batch->expiry_date->lt($expiry))) {
                        $expiry = $batch->expiry_date->copy();
                    }
                }
            }

            $material = round($material, 2);
            $unit = round(($material + $labour) / $quantity, 4);
            $out = $this->inventory->receiveStock(
                productId: $product->id, warehouseId: $warehouseId, batchNo: $order->batch_no,
                expiryDate: $expiry?->toDateString(), costPrice: $unit, quantity: $quantity, reference: $order,
            );
            $order->update(['material_cost' => $material, 'unit_cost' => $unit, 'expiry_date' => $expiry, 'output_batch_id' => $out->id]);

            if ($labour > 0) {
                $this->ledger->post(
                    ['type' => 'auto', 'entry_date' => $date, 'dealer_id' => null, 'source' => $order, 'source_event' => 'labour',
                        'narration' => __('Packing labour, :n', ['n' => $order->number])],
                    [['account' => 'inventory', 'debit' => round($labour, 2)], ['account' => $paidFromId, 'credit' => round($labour, 2)]],
                );
            }

            return $order->fresh(['items', 'outputBatch']);
        });
    }

    public function nextBatchNo(Product $product, string $date, string $kind): string
    {
        $stem = ($kind === 'repack' ? 'RP-' : 'KT-').strtoupper($product->sku).'-'.Carbon::parse($date)->format('ymd');
        $n = AssemblyOrder::where('batch_no', 'like', $stem.'-%')->count() + 1;

        return $stem.'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}
