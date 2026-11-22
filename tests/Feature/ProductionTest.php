<?php

namespace Tests\Feature;

use App\Livewire\Delivery\Create as DeliveryCreate;
use App\Livewire\Production\Formulas;
use App\Livewire\Production\Orders\Index as OrdersIndex;
use App\Livewire\Production\Orders\Show as OrderShow;
use App\Livewire\Production\Planning;
use App\Livewire\Production\Trace;
use App\Livewire\Production\Transfers;
use App\Livewire\Sales\Orders\Create as SalesCreate;
use App\Livewire\Sales\Orders\Show as SalesShow;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\Batch;
use App\Models\Factory;
use App\Models\Product;
use App\Models\Production\Bom;
use App\Models\Production\ProductionIssue;
use App\Models\Production\ProductionOrder;
use App\Models\Production\StockTransfer;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\Production\BatchTrace;
use App\Services\Production\ProductionService;
use App\Services\Production\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 of the ERP plan: formulas, batch production with QC, costing and
 * accounting, stock transfers and batch tracing.
 */
class ProductionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Factory $factory;
    private Warehouse $raw;
    private Warehouse $fg;
    private Warehouse $damage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->admin = $this->userWithRole('Admin');
        $this->actingAs($this->admin);

        $this->factory = Factory::create(['code' => 'F1', 'name' => 'Factory 1', 'active' => true]);
        $this->raw = Warehouse::create(['name' => 'F1 Raw', 'type' => 'central', 'factory_id' => $this->factory->id, 'purpose' => 'raw_material', 'active' => true]);
        $this->fg = Warehouse::create(['name' => 'F1 FG', 'type' => 'central', 'factory_id' => $this->factory->id, 'purpose' => 'finished_goods', 'active' => true]);
        $this->damage = Warehouse::create(['name' => 'F1 Damage', 'type' => 'central', 'factory_id' => $this->factory->id, 'purpose' => 'damage', 'active' => true]);
    }

    private function stockIn(Product $p, Warehouse $w, float $qty, string $batch, float $cost, ?string $expiry = null): Batch
    {
        return app(InventoryService::class)->receiveStock(
            productId: $p->id, warehouseId: $w->id, batchNo: $batch, expiryDate: $expiry,
            costPrice: $cost, quantity: $qty, reference: $p,
        );
    }

    /** Fertiliser: 100 kg needs 60 kg urea (+5% wastage) and 40 kg potash. */
    private function setUpFormula(): array
    {
        $fg = $this->product(['type' => 'finished_good', 'sku' => 'FERT', 'name' => 'Mix fertiliser', 'shelf_life_days' => 365]);
        $urea = $this->product(['type' => 'raw_material', 'name' => 'Urea', 'purchase_price' => 20]);
        $potash = $this->product(['type' => 'raw_material', 'name' => 'Potash', 'purchase_price' => 30]);

        Livewire::test(Formulas::class)->call('create')
            ->set('product_id', $fg->id)->set('output_quantity', '100')->set('expected_yield', '98')
            ->set('items', [
                ['product_id' => $urea->id, 'quantity' => '60', 'wastage_percent' => '5'],
                ['product_id' => $potash->id, 'quantity' => '40', 'wastage_percent' => '0'],
            ])
            ->call('save')->assertHasNoErrors();

        return [$fg, $urea, $potash, Bom::where('product_id', $fg->id)->first()];
    }

    private function balance(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    public function test_formula_versions_keep_old_ones_and_compute_requirements(): void
    {
        [$fg, $urea, $potash, $bom] = $this->setUpFormula();

        $this->assertSame(1, $bom->version);
        $this->assertTrue($bom->is_default);
        $req = $bom->requirements(200);
        $this->assertEqualsWithDelta(126, $req[$urea->id], 0.001);   // 60 × 2 × 1.05
        $this->assertEqualsWithDelta(80, $req[$potash->id], 0.001);

        // A new version from the old one becomes the default; v1 stays as it was.
        Livewire::test(Formulas::class)->call('create', null, $bom->id)
            ->set('items.1.quantity', '45')->call('save')->assertHasNoErrors();
        $v2 = Bom::where('product_id', $fg->id)->where('version', 2)->first();
        $this->assertTrue($v2->is_default);
        $this->assertFalse($bom->fresh()->is_default);
        $this->assertEqualsWithDelta(40, (float) $bom->fresh()->items->firstWhere('product_id', $potash->id)->quantity, 0.001);

        // A raw material cannot have a formula, nor be its own material.
        Livewire::test(Formulas::class)->call('create')->set('product_id', $urea->id)->set('output_quantity', '1')
            ->set('items', [['product_id' => $potash->id, 'quantity' => '1', 'wastage_percent' => '0']])
            ->call('save')->assertHasErrors('product_id');
        Livewire::test(Formulas::class)->call('create')->set('product_id', $fg->id)->set('output_quantity', '1')
            ->set('items', [['product_id' => $fg->id, 'quantity' => '1', 'wastage_percent' => '0']])
            ->call('save')->assertHasErrors('items.0.product_id');
    }

    public function test_full_batch_issue_qc_complete_costing_and_accounting(): void
    {
        [$fg, $urea, $potash, $bom] = $this->setUpFormula();
        // Two urea batches: the one expiring first must be used first.
        $this->stockIn($urea, $this->raw, 40, 'U-LATE', 22, today()->addYear()->toDateString());
        $this->stockIn($urea, $this->raw, 40, 'U-SOON', 20, today()->addMonth()->toDateString());
        $this->stockIn($potash, $this->raw, 100, 'P-1', 30);

        Livewire::test(OrdersIndex::class)->call('create')
            ->set('factory_id', $this->factory->id)->set('product_id', $fg->id)
            ->assertSet('bom_id', $bom->id)->assertSet('planned_quantity', '100')
            ->call('save')->assertHasNoErrors();
        $order = ProductionOrder::firstOrFail();
        $this->assertSame('planned', $order->status);
        $this->assertSame($this->raw->id, $order->raw_warehouse_id);
        $this->assertSame($this->fg->id, $order->output_warehouse_id);
        $this->assertStringStartsWith('FERT-', $order->batch_no);
        $this->assertSame(today()->addDays(365)->toDateString(), $order->expiry_date->toDateString());

        // Issue everything: urea 63 = 40 from U-SOON + 23 from U-LATE.
        Livewire::test(OrderShow::class, ['order' => $order])->call('openIssue')->call('issue')->assertHasNoErrors();
        $order->refresh();
        $this->assertSame('in_progress', $order->status);
        $this->assertEqualsWithDelta(40, (float) ProductionIssue::whereHas('batch', fn ($b) => $b->where('batch_no', 'U-SOON'))->sum('quantity'), 0.001);
        $this->assertEqualsWithDelta(23, (float) ProductionIssue::whereHas('batch', fn ($b) => $b->where('batch_no', 'U-LATE'))->sum('quantity'), 0.001);
        $material = 40 * 20 + 23 * 22 + 40 * 30;   // 2506
        $this->assertEqualsWithDelta($material, (float) $order->material_cost, 0.01);
        $this->assertEqualsWithDelta($material, $this->balance('wip'), 0.01);

        // Nothing goes into stock before a passed QC.
        Livewire::test(OrderShow::class, ['order' => $order])->call('openComplete')->set('good', '95')->call('complete')->assertHasErrors('complete');
        Livewire::test(OrderShow::class, ['order' => $order])->call('openQc')->set('qc_result', 'fail')->call('saveQc');
        Livewire::test(OrderShow::class, ['order' => $order->fresh()])->call('openComplete')->set('good', '95')->call('complete')->assertHasErrors('complete');
        Livewire::test(OrderShow::class, ['order' => $order->fresh()])->call('openQc')->set('qc_result', 'pass')->set('qc_parameters', 'Moisture 2%')->call('saveQc');

        Livewire::test(OrderShow::class, ['order' => $order->fresh()])->call('openComplete')
            ->set('good', '95')->set('rejected', '3')->set('labour', '300')->set('overhead', '144')
            ->call('complete')->assertHasNoErrors();
        $order->refresh();

        $this->assertSame('completed', $order->status);
        $this->assertEqualsWithDelta(95.0, $order->yieldPercent(), 0.01);
        $total = $material + 300 + 144;    // 2950
        $this->assertEqualsWithDelta($total, (float) $order->total_cost, 0.01);
        $this->assertEqualsWithDelta($total / 95, (float) $order->unit_cost, 0.001);

        $out = Batch::where('warehouse_id', $this->fg->id)->where('batch_no', $order->batch_no)->first();
        $this->assertEqualsWithDelta(95, (float) $out->quantity_available, 0.001);
        $this->assertEqualsWithDelta($total / 95, (float) $out->cost_price, 0.01);   // batch cost is kept to 2 decimals
        $this->assertSame($order->expiry_date->toDateString(), $out->expiry_date->toDateString());
        $rej = Batch::where('warehouse_id', $this->damage->id)->first();
        $this->assertSame($order->batch_no.'-REJ', $rej->batch_no);
        $this->assertEqualsWithDelta(0, (float) $rej->cost_price, 0.001);

        // Accounting: WIP cleared, the goods on the books at full cost.
        $this->assertEqualsWithDelta(0, $this->balance('wip'), 0.01);
        $this->assertEqualsWithDelta(-444, $this->balance('production_applied'), 0.01);
        // Inventory: − materials issued + finished goods at full cost (the raw stock here had no purchase entry).
        $this->assertEqualsWithDelta($total - $material, $this->balance('inventory'), 0.01);
    }

    public function test_issue_fails_when_stock_is_short_and_cancel_rules(): void
    {
        [$fg, $urea, $potash, $bom] = $this->setUpFormula();
        $this->stockIn($urea, $this->raw, 10, 'U1', 20);
        $order = app(ProductionService::class)->create(['factory_id' => $this->factory->id, 'product_id' => $fg->id, 'bom_id' => $bom->id, 'planned_quantity' => 100, 'mfg_date' => today()->toDateString()]);

        Livewire::test(OrderShow::class, ['order' => $order])->call('openIssue')->call('issue')->assertHasErrors('issue');
        $this->assertSame(0, ProductionIssue::count());
        $this->assertEqualsWithDelta(10, (float) Batch::where('batch_no', 'U1')->value('quantity_available'), 0.001);

        // Planning shows the shortfall.
        Livewire::test(Planning::class)->set('factory_id', $this->factory->id)
            ->assertSee('Urea')->assertViewHas('rows', fn ($rows) => abs($rows->firstWhere('product.id', $urea->id)['short'] - 53) < 0.001);

        Livewire::test(OrderShow::class, ['order' => $order])->call('cancel');
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_zero_good_output_writes_materials_off(): void
    {
        [$fg, $urea, $potash, $bom] = $this->setUpFormula();
        $this->stockIn($urea, $this->raw, 100, 'U1', 20);
        $this->stockIn($potash, $this->raw, 100, 'P1', 30);
        $svc = app(ProductionService::class);
        $order = $svc->create(['factory_id' => $this->factory->id, 'product_id' => $fg->id, 'bom_id' => $bom->id, 'planned_quantity' => 100, 'mfg_date' => today()->toDateString()]);
        $svc->issue($order);
        $svc->complete($order->fresh(), 0, 100, 0, 0);

        $this->assertEqualsWithDelta(0, $this->balance('wip'), 0.01);
        $this->assertEqualsWithDelta(63 * 20 + 40 * 30, $this->balance('stock_loss'), 0.01);
        $this->assertNull(Batch::where('warehouse_id', $this->fg->id)->first());
    }

    public function test_transfer_keeps_batch_and_trace_goes_both_ways(): void
    {
        [$fg, $urea, $potash, $bom] = $this->setUpFormula();
        $this->stockIn($urea, $this->raw, 100, 'U1', 20);
        $this->stockIn($potash, $this->raw, 100, 'P1', 30);
        $svc = app(ProductionService::class);
        $order = $svc->create(['factory_id' => $this->factory->id, 'product_id' => $fg->id, 'bom_id' => $bom->id, 'planned_quantity' => 100, 'mfg_date' => today()->toDateString()]);
        $svc->issue($order);
        $svc->qc($order->fresh(), 'pass', null, null);
        $svc->complete($order->fresh(), 100, 0, 0, 0);

        // Factory → depot keeps the batch number, expiry and cost.
        $depot = $this->warehouse();
        $from = Batch::where('warehouse_id', $this->fg->id)->first();
        Livewire::test(Transfers::class)->call('create')
            ->set('from_id', $this->fg->id)->set('to_id', $depot->id)->set("qty.{$from->id}", '30')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(1, StockTransfer::count());
        $moved = Batch::where('warehouse_id', $depot->id)->where('batch_no', $order->batch_no)->first();
        $this->assertEqualsWithDelta(30, (float) $moved->quantity_available, 0.001);
        $this->assertEqualsWithDelta((float) $from->cost_price, (float) $moved->cost_price, 0.001);
        $this->assertEqualsWithDelta(70, (float) $from->fresh()->quantity_available, 0.001);

        // Sell 5 from the depot.
        $fg->update(['sale_price' => 50]);
        $retailer = $this->retailer(['name' => 'Green Shop']);
        Livewire::test(SalesCreate::class)->set('retailer_id', $retailer->id)->set('warehouse_id', $depot->id)
            ->call('addProduct', $fg->id)->set('items.0.quantity', 5)->call('save')->assertHasNoErrors();
        $so = SalesOrder::latest('id')->first();
        Livewire::test(SalesShow::class, ['salesOrder' => $so])->call('approve');
        Livewire::test(DeliveryCreate::class, ['salesOrder' => $so->fresh()])->call('save')->assertHasNoErrors();

        $t = app(BatchTrace::class)->trace($order->batch_no);
        $this->assertSame($order->id, $t['order']->id);
        $this->assertEqualsCanonicalizing(['U1', 'P1'], array_column($t['inputs'], 'batch_no'));
        $this->assertCount(1, $t['deliveries']);
        $this->assertSame('Green Shop', $t['deliveries'][0]['customer']);
        $this->assertEqualsWithDelta(95, collect($t['stock'])->sum('available'), 0.001);

        // Forward from a raw material batch to the batch it went into.
        $u = app(BatchTrace::class)->trace('U1');
        $this->assertSame($order->id, $u['used_in'][0]['order']->id);

        Livewire::test(Trace::class)->set('batch', $order->batch_no)->assertSee('Green Shop')->assertSee('P1');
        $this->get(route('production.orders.pdf', $order))->assertOk();
        $this->get(route('production.orders.show', $order))->assertOk();
    }

    public function test_transfer_between_owners_is_refused(): void
    {
        $p = $this->product();
        $this->stockIn($p, $this->fg, 10, 'X1', 5);
        $dealer = \App\Models\Dealer::create(['code' => 'D1', 'name' => 'Dealer', 'phone' => '01700000000']);
        $theirs = Warehouse::create(['name' => 'Dealer store', 'type' => 'central', 'dealer_id' => $dealer->id, 'active' => true]);
        $batch = Batch::where('batch_no', 'X1')->first();

        $this->expectException(\RuntimeException::class);
        app(StockTransferService::class)->create($this->fg->id, $theirs->id, today()->toDateString(), [['batch_id' => $batch->id, 'quantity' => 5]]);
    }

    public function test_factory_manager_can_run_production_but_not_accounts(): void
    {
        $fm = $this->userWithRole('Factory Manager');
        $this->actingAs($fm);
        $this->get(route('production.orders.index'))->assertOk();
        $this->get(route('production.formulas'))->assertOk();
        $this->get(route('production.machines'))->assertOk();
        $this->get(route('production.planning'))->assertOk();
        $this->get(route('inventory.transfers'))->assertOk();
        $this->get(route('accounts.vouchers.index'))->assertForbidden();
        $this->get(route('hr.employees.index'))->assertForbidden();
    }
}
