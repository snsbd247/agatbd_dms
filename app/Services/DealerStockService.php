<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Dealer;
use App\Models\Retailer;
use App\Models\Warehouse;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Stock moving between the head office and a dealer.
 *
 * Goods the head office delivers to a dealer's account arrive in the dealer's
 * main warehouse as batches with the same batch number and expiry, so a batch
 * can be traced from the factory to the dealer's customers. Their cost for
 * the dealer is the price the dealer paid (after its discount).
 */
class DealerStockService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * @param  array<int, array{product_id: int, batch_id: int|null, quantity: float, unit_price: float}>  $lines
     * @param  float  $discountShare  0..1, the part of the price taken off as discount
     */
    public function receiveFromHeadOffice(Retailer $account, array $lines, float $discountShare, Model $reference): void
    {
        if (! $account->account_dealer_id) {
            return;
        }

        Tenant::bypass(function () use ($account, $lines, $discountShare, $reference) {
            $warehouse = $this->dealerWarehouse($account->account_dealer_id);

            foreach ($lines as $line) {
                $source = $line['batch_id'] ? Batch::find($line['batch_id']) : null;

                $this->inventory->receiveStock(
                    productId: $line['product_id'],
                    warehouseId: $warehouse->id,
                    batchNo: $source?->batch_no ?? 'HO-'.$reference->getKey(),
                    expiryDate: $source?->expiry_date ? \Carbon\Carbon::parse($source->expiry_date)->toDateString() : null,
                    costPrice: round((float) $line['unit_price'] * (1 - $discountShare), 4),
                    quantity: (float) $line['quantity'],
                    reference: $reference,
                );
            }
        });
    }

    /**
     * Goods a dealer sends back to the head office leave the dealer's stock
     * (FIFO across the dealer's warehouses that hold the product).
     */
    public function returnToHeadOffice(Retailer $account, int $productId, float $quantity, Model $reference): void
    {
        if (! $account->account_dealer_id || $quantity <= 0) {
            return;
        }

        Tenant::bypass(function () use ($account, $productId, $quantity, $reference) {
            $remaining = $quantity;

            $warehouses = Warehouse::where('dealer_id', $account->account_dealer_id)
                ->whereHas('stocks', fn ($q) => $q->where('product_id', $productId)->where('quantity', '>', 0))
                ->with(['stocks' => fn ($q) => $q->where('product_id', $productId)])
                ->orderBy('id')
                ->get();

            foreach ($warehouses as $warehouse) {
                $take = min($remaining, (float) $warehouse->stocks->first()->quantity);

                if ($take > 0) {
                    $this->inventory->consumeStock($productId, $warehouse->id, $take, $reference);
                    $remaining -= $take;
                }

                if ($remaining <= 0.0005) {
                    return;
                }
            }

            throw new \RuntimeException(__('The dealer does not have enough of this product in stock to send back.'));
        });
    }

    public function dealerWarehouse(int $dealerId): Warehouse
    {
        $warehouse = Dealer::findOrFail($dealerId)->mainWarehouse();

        if (! $warehouse) {
            throw new \RuntimeException(__('This dealer has no active warehouse to receive the goods. Add one first.'));
        }

        return $warehouse;
    }
}
