<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Stock;
use App\Models\StockLedger;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function stockOf($product): float
    {
        return (float) Stock::where('product_id', $product->id)->value('quantity');
    }

    public function test_receiving_creates_batch_stock_and_ledger(): void
    {
        $p = $this->product();
        $this->receive($p, 50, 'B1');

        $this->assertSame(50.0, $this->stockOf($p));
        $this->assertSame(50.0, (float) Batch::where('product_id', $p->id)->value('quantity_available'));
        $this->assertDatabaseHas('stock_ledger', ['product_id' => $p->id, 'type' => 'in', 'quantity' => 50, 'balance_after' => 50]);
    }

    public function test_consuming_takes_oldest_batch_first(): void
    {
        $p = $this->product();
        $this->receive($p, 10, 'OLD');
        $this->travel(1)->minutes();
        $this->receive($p, 10, 'NEW');

        $consumed = app(InventoryService::class)->consumeStock($p->id, $this->warehouse()->id, 15, $p);

        $this->assertSame(5.0, $this->stockOf($p));
        $this->assertSame(0.0, (float) Batch::where('batch_no', 'OLD')->value('quantity_available'));
        $this->assertSame(5.0, (float) Batch::where('batch_no', 'NEW')->value('quantity_available'));
        $this->assertSame([10.0, 5.0], array_map('floatval', array_column($consumed, 'quantity')));
    }

    public function test_cannot_consume_more_than_stock(): void
    {
        $p = $this->product();
        $this->receive($p, 3);

        $this->expectException(\RuntimeException::class);
        app(InventoryService::class)->consumeStock($p->id, $this->warehouse()->id, 4, $p);
    }

    public function test_adjustment_add_without_batch_opens_adjustment_batch(): void
    {
        $p = $this->product();
        $adj = app(InventoryService::class)->adjustStock($this->warehouse()->id, $p->id, null, 7, 'Found stock', null, today()->toDateString());

        $this->assertSame(7.0, $this->stockOf($p));
        $this->assertStringStartsWith('ADJ-', $adj->batch->batch_no);
        $this->assertDatabaseHas('stock_ledger', ['type' => 'adjustment', 'quantity' => 7, 'reference_id' => $adj->id]);
    }

    public function test_adjustment_remove_keeps_batches_in_sync_with_stock(): void
    {
        $p = $this->product();
        $this->receive($p, 10, 'A');
        $this->travel(1)->minutes();
        $this->receive($p, 10, 'B');

        app(InventoryService::class)->adjustStock($this->warehouse()->id, $p->id, null, -12, 'Damaged', null, today()->toDateString());

        $this->assertSame(8.0, $this->stockOf($p));
        $this->assertSame(8.0, (float) Batch::where('product_id', $p->id)->sum('quantity_available'));
        $this->assertSame(0.0, (float) Batch::where('batch_no', 'A')->value('quantity_available'));
        $this->assertSame(-12.0, (float) StockLedger::where('type', 'adjustment')->value('quantity'));
    }

    public function test_adjustment_cannot_remove_more_than_stock_or_batch(): void
    {
        $p = $this->product();
        $this->receive($p, 5, 'A');
        $batch = Batch::first();
        $svc = app(InventoryService::class);

        try {
            $svc->adjustStock($this->warehouse()->id, $p->id, null, -6, 'x', null, today()->toDateString());
            $this->fail('Removing more than stock should fail');
        } catch (\RuntimeException) {
        }

        $this->expectException(\RuntimeException::class);
        $svc->adjustStock($this->warehouse()->id, $p->id, $batch->id, -6, 'x', null, today()->toDateString());
    }
}
