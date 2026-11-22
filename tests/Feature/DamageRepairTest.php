<?php

namespace Tests\Feature;

use App\Livewire\Damage\Index as DamageIndex;
use App\Livewire\Damage\Repairs;
use App\Livewire\Damage\Show as DamageShow;
use App\Livewire\Production\Maintenance;
use App\Livewire\Returns\Receives\Create as ReturnReceiveCreate;
use App\Livewire\Sales\Orders\Create as SalesCreate;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\Batch;
use App\Models\Damage\DamageRecord;
use App\Models\Damage\RepairJob;
use App\Models\Factory;
use App\Models\Product;
use App\Models\Production\Machine;
use App\Models\Production\MachineMaintenance;
use App\Models\Returns\ReturnRequest;
use App\Models\Returns\ReturnRequestItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Damage\DamageService;
use App\Services\InventoryService;
use App\Services\Production\ProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5 of the ERP plan: the damage register, decisions, repair jobs and
 * machine maintenance, with their stock movements and accounting.
 */
class DamageRepairTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $main;
    private Warehouse $damage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->admin = $this->userWithRole('Admin');
        $this->actingAs($this->admin);

        $this->main = $this->warehouse();
        $this->damage = Warehouse::create(['name' => 'Damage store', 'type' => 'central', 'purpose' => 'damage', 'active' => true]);
    }

    private function balance(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    /** 10 bags reported damaged out of a batch of 20 at 50 each, and approved. */
    private function approvedDamage(): array
    {
        $p = $this->product(['name' => 'Seed bag']);
        $this->receive($p, 20, 'S-1', 50);
        $batch = Batch::where('batch_no', 'S-1')->first();

        Livewire::test(DamageIndex::class)->call('create')
            ->set('warehouse_id', $this->main->id)->set('batch_id', $batch->id)->set('quantity', '10')
            ->set('reason', 'Bags torn by rats')->call('save')->assertHasNoErrors();

        return [$p, DamageRecord::firstOrFail(), $batch];
    }

    public function test_report_needs_approval_then_moves_stock_to_damage_store(): void
    {
        $p = $this->product();
        $this->receive($p, 20, 'S-1', 50);
        $batch = Batch::where('batch_no', 'S-1')->first();

        // A storekeeper without the approve right only reports; nothing moves.
        $keeper = $this->userWithRole('Manager');
        $keeper->revokePermissionTo('damage.approve');
        \Spatie\Permission\Models\Role::findByName('Manager')->revokePermissionTo('damage.approve');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($keeper->fresh());
        Livewire::test(DamageIndex::class)->call('create')
            ->set('warehouse_id', $this->main->id)->set('batch_id', $batch->id)->set('quantity', '25')->set('reason', 'Wet')
            ->call('save')->assertHasErrors('quantity');   // more than the batch has
        Livewire::test(DamageIndex::class)->call('create')
            ->set('warehouse_id', $this->main->id)->set('batch_id', $batch->id)->set('quantity', '4')->set('reason', 'Wet')
            ->call('save')->assertHasNoErrors();
        $record = DamageRecord::firstOrFail();
        $this->assertSame('reported', $record->status);
        $this->assertEqualsWithDelta(20, (float) $batch->fresh()->quantity_available, 0.001);

        // The approver approves: the 4 move, same batch number and cost.
        $this->actingAs($this->admin);
        Livewire::test(DamageIndex::class)->call('approve', $record->id);
        $record->refresh();
        $this->assertSame('approved', $record->status);
        $this->assertSame($this->damage->id, $record->warehouse_id);
        $this->assertEqualsWithDelta(16, (float) $batch->fresh()->quantity_available, 0.001);
        $moved = Batch::where('warehouse_id', $this->damage->id)->first();
        $this->assertSame('S-1', $moved->batch_no);
        $this->assertEqualsWithDelta(50, (float) $moved->cost_price, 0.001);
        $this->assertEqualsWithDelta(0, $this->balance('stock_loss'), 0.01);   // no loss until decided

        // Damage stores cannot be sold from.
        Livewire::test(SalesCreate::class)->set('retailer_id', $this->retailer()->id)->set('warehouse_id', $this->damage->id)
            ->call('addProduct', $p->id)->set('items.0.quantity', 1)->call('save')->assertHasErrors('warehouse_id');
    }

    public function test_decisions_in_parts_destroy_discount_and_repair(): void
    {
        [$p, $record] = $this->approvedDamage();
        $this->assertSame('approved', $record->status);

        // 2 destroyed: 100 written off.
        Livewire::test(DamageShow::class, ['record' => $record])->call('openDecide', 'destroy')->set('quantity', '2')->call('decide')->assertHasNoErrors();
        $this->assertEqualsWithDelta(100, $this->balance('stock_loss'), 0.01);

        // 3 sold at a discount, recovery 20 each: 90 written off, batch S-1-D at 20.
        Livewire::test(DamageShow::class, ['record' => $record->fresh()])->call('openDecide', 'discount_sale')
            ->set('quantity', '3')->set('recovery_price', '20')->set('target_warehouse_id', $this->main->id)->call('decide')->assertHasNoErrors();
        $d = Batch::where('batch_no', 'S-1-D')->first();
        $this->assertSame($this->main->id, $d->warehouse_id);
        $this->assertEqualsWithDelta(20, (float) $d->cost_price, 0.001);
        $this->assertEqualsWithDelta(190, $this->balance('stock_loss'), 0.01);

        // More than is left cannot be decided.
        Livewire::test(DamageShow::class, ['record' => $record->fresh()])->call('openDecide', 'repair')->set('quantity', '6')->call('decide')->assertHasErrors('decide');

        // The last 5 go for repair at an outside workshop.
        Livewire::test(DamageShow::class, ['record' => $record->fresh()])->call('openDecide', 'repair')
            ->set('mode', 'outsourced')->set('vendor', 'Rahman Bag House')->set('estimated_cost', '100')->call('decide')->assertHasNoErrors();
        $record->refresh();
        $this->assertEqualsWithDelta(0, (float) $record->quantity_open, 0.001);
        $this->assertSame('approved', $record->status);   // still open while the repair runs
        $job = RepairJob::firstOrFail();
        $this->assertSame('outsourced', $job->mode);

        // 4 come back repaired for 100 cash; 1 is scrapped.
        $cash = Account::bySystemKey('cash');
        Livewire::test(Repairs::class)->call('openComplete', $job->id)
            ->set('repaired', '4')->set('cost', '100')->set('paid_from_id', $cash->id)->set('return_warehouse_id', $this->main->id)
            ->call('complete')->assertHasNoErrors();
        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertEqualsWithDelta(1, (float) $job->scrapped_quantity, 0.001);
        $back = Batch::find($job->output_batch_id);
        $this->assertSame('S-1', $back->batch_no);
        $this->assertSame($this->main->id, $back->warehouse_id);
        $this->assertEqualsWithDelta((4 * 50 + 100) / 4, (float) $back->cost_price, 0.001);
        $this->assertEqualsWithDelta(240, $this->balance('stock_loss'), 0.01);   // + 1 scrapped × 50
        $this->assertEqualsWithDelta(-100, $this->balance('cash'), 0.01);
        $this->assertSame('closed', $record->fresh()->status);
        $this->assertEqualsWithDelta(0, (float) Batch::where('warehouse_id', $this->damage->id)->sum('quantity_available'), 0.001);

        $this->get(route('damage.show', $record))->assertOk();
        $this->get(route('damage.repairs'))->assertOk();
    }

    public function test_cancelled_repair_reopens_the_quantity(): void
    {
        [$p, $record] = $this->approvedDamage();
        $svc = app(DamageService::class);
        $svc->decide($record, 'reprocess', 10);
        $job = RepairJob::firstOrFail();
        $svc->cancelRepair($job);
        $this->assertEqualsWithDelta(10, (float) $record->fresh()->quantity_open, 0.001);
        $this->assertSame('approved', $record->fresh()->status);
        $this->expectException(\RuntimeException::class);
        $svc->completeRepair($job->fresh(), 10, 0, null, null);
    }

    public function test_nothing_repaired_writes_off_goods_and_cost(): void
    {
        [$p, $record] = $this->approvedDamage();
        $svc = app(DamageService::class);
        $svc->decide($record, 'repair', 10);
        $svc->completeRepair(RepairJob::firstOrFail(), 0, 30, Account::bySystemKey('cash')->id, null);
        $this->assertEqualsWithDelta(500, $this->balance('stock_loss'), 0.01);
        $this->assertEqualsWithDelta(30, $this->balance('repairs'), 0.01);
        $this->assertEqualsWithDelta(-500, $this->balance('inventory'), 0.01);
    }

    public function test_damaged_returns_and_production_rejects_join_the_register(): void
    {
        // A damaged customer return goes into the damage store at no value.
        $p = $this->product();
        $r = $this->retailer(['current_due' => 500]);
        $request = ReturnRequest::create(['number' => 'RET-1', 'retailer_id' => $r->id, 'request_date' => today(), 'status' => 'approved']);
        ReturnRequestItem::create(['return_request_id' => $request->id, 'product_id' => $p->id, 'quantity' => 2, 'unit_price' => 50]);
        $c = Livewire::test(ReturnReceiveCreate::class, ['returnRequest' => $request]);
        $lines = $c->get('lines');
        $lines[0]['disposition'] = 'damage';
        $c->set('lines', $lines)->call('save')->assertHasNoErrors();

        $rec = DamageRecord::where('source', 'return')->firstOrFail();
        $this->assertSame('approved', $rec->status);
        $this->assertSame($this->damage->id, $rec->warehouse_id);
        $this->assertEqualsWithDelta(2, (float) $rec->quantity, 0.001);
        $this->assertEqualsWithDelta(0, (float) $rec->unit_cost, 0.001);
        $this->assertSame(400.0, (float) $r->fresh()->current_due);

        // Production rejects.
        $factory = Factory::create(['code' => 'F1', 'name' => 'F1', 'active' => true]);
        $raw = Warehouse::create(['name' => 'Raw', 'type' => 'central', 'factory_id' => $factory->id, 'purpose' => 'raw_material', 'active' => true]);
        Warehouse::create(['name' => 'FG', 'type' => 'central', 'factory_id' => $factory->id, 'purpose' => 'finished_goods', 'active' => true]);
        $fdmg = Warehouse::create(['name' => 'F1 damage', 'type' => 'central', 'factory_id' => $factory->id, 'purpose' => 'damage', 'active' => true]);
        $fg = $this->product(['type' => 'finished_good']);
        $mat = $this->product(['type' => 'raw_material']);
        $bom = \App\Models\Production\Bom::create(['product_id' => $fg->id, 'version' => 1, 'output_quantity' => 10, 'expected_yield' => 100, 'is_default' => true, 'active' => true]);
        $bom->items()->create(['product_id' => $mat->id, 'quantity' => 10, 'wastage_percent' => 0]);
        app(InventoryService::class)->receiveStock(productId: $mat->id, warehouseId: $raw->id, batchNo: 'M1', expiryDate: null, costPrice: 5, quantity: 10, reference: $mat);
        $svc = app(ProductionService::class);
        $order = $svc->create(['factory_id' => $factory->id, 'product_id' => $fg->id, 'bom_id' => $bom->id, 'planned_quantity' => 10, 'mfg_date' => today()->toDateString()]);
        $svc->issue($order);
        $svc->qc($order->fresh(), 'pass', null, null);
        $svc->complete($order->fresh(), 8, 2, 0, 0);

        $rej = DamageRecord::where('source', 'production')->firstOrFail();
        $this->assertSame($fdmg->id, $rej->warehouse_id);
        $this->assertSame($order->batch_no.'-REJ', $rej->batch_no);

        // Reprocessed and back in the finished goods store under the batch number.
        app(DamageService::class)->decide($rej, 'reprocess', 2);
        app(DamageService::class)->completeRepair(RepairJob::firstOrFail(), 2, 0, null, null);
        $this->assertNotNull(Batch::where('batch_no', $order->batch_no)->where('quantity_available', 2)->first());
    }

    public function test_machine_maintenance_posts_cost_and_shows_due(): void
    {
        $factory = Factory::create(['code' => 'F1', 'name' => 'F1', 'active' => true]);
        $m = Machine::create(['factory_id' => $factory->id, 'code' => 'MX1', 'name' => 'Mixer 1', 'kind' => 'machine', 'active' => true]);
        $cash = Account::bySystemKey('cash');

        Livewire::test(Maintenance::class)->call('create', $m->id)
            ->set('type', 'breakdown')->set('description', 'Motor rewinding')->set('downtime_hours', '6')
            ->set('cost', '2500')->set('paid_from_id', $cash->id)->set('next_due_date', today()->addMonth()->toDateString())
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1, MachineMaintenance::count());
        $this->assertEqualsWithDelta(2500, $this->balance('repairs'), 0.01);
        $this->assertEqualsWithDelta(-2500, $this->balance('cash'), 0.01);
        Livewire::test(Maintenance::class)->assertSee('Mixer 1')->assertSee(today()->addMonth()->format('d M Y'));
        $this->get(route('production.maintenance'))->assertOk();
    }

    public function test_dealer_sees_only_own_damage(): void
    {
        $dealer = \App\Models\Dealer::create(['code' => 'D1', 'name' => 'Dealer', 'phone' => '01700000000']);
        $theirs = Warehouse::create(['name' => 'Dealer store', 'type' => 'central', 'dealer_id' => $dealer->id, 'active' => true]);
        Warehouse::create(['name' => 'Dealer damage', 'type' => 'central', 'dealer_id' => $dealer->id, 'purpose' => 'damage', 'active' => true]);
        [$p, $ours] = $this->approvedDamage();

        // The dealer reports and approves damage in its own warehouse.
        $user = app(\App\Services\DealerService::class)->addLogin($dealer, ['name' => 'Dealer user', 'email' => 'd@d.test', 'password' => 'secret-123']);
        $this->actingAs($user);
        \App\Support\Tenant::flush();
        $dp = $this->product();
        app(InventoryService::class)->receiveStock(productId: $dp->id, warehouseId: $theirs->id, batchNo: 'DB1', expiryDate: null, costPrice: 10, quantity: 5, reference: $dp);
        $theirRecord = app(DamageService::class)->report(Batch::where('batch_no', 'DB1')->value('id'), 1, 'warehouse', 'Broken', today()->toDateString());
        app(DamageService::class)->approve($theirRecord);
        $this->assertSame('Dealer damage', $theirRecord->fresh()->warehouse->name);   // the dealer's own damage store
        $this->assertNull(DamageRecord::find($ours->id));                              // HQ's record is invisible to the dealer
        $this->get(route('damage.index'))->assertOk()->assertSee($theirRecord->number)->assertDontSee($ours->number);
        $this->get(route('damage.show', $ours))->assertNotFound();
        $this->get(route('production.maintenance'))->assertForbidden();
    }
}
