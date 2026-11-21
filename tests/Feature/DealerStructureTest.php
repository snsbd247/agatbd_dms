<?php

namespace Tests\Feature;

use App\Livewire\Dealers\Index as DealersIndex;
use App\Livewire\Dealers\Show as DealersShow;
use App\Livewire\Delivery\Create as DeliveryCreate;
use App\Livewire\Portal\OrderCreate as PortalOrderCreate;
use App\Livewire\Retailers\Index as RetailersIndex;
use App\Livewire\Returns\Receives\Create as ReturnReceiveCreate;
use App\Livewire\Sales\Orders\Create as OrderCreate;
use App\Livewire\Sales\Orders\Show as OrderShow;
use App\Models\Area;
use App\Models\Batch;
use App\Models\Dealer;
use App\Models\Representative;
use App\Models\Retailer;
use App\Models\Returns\ReturnRequest;
use App\Models\Returns\ReturnRequestItem;
use App\Models\Sales\SalesOrder;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SalesOrderPending;
use App\Services\DealerService;
use App\Services\InventoryService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1 of the ERP plan: the head office sells to dealers, each dealer
 * runs its own panel and sees only its own records.
 */
class DealerStructureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Area $area;
    private Warehouse $hq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole('Admin');
        $this->actingAs($this->admin);
        $this->area = Area::firstOrCreate(['code' => 'AR1'], ['name' => 'Area 1']);
        $this->hq = $this->warehouse();
    }

    protected function tearDown(): void
    {
        Tenant::flush();
        parent::tearDown();
    }

    private function openDealer(string $code, ?string $email = null): Dealer
    {
        return app(DealerService::class)->create(
            ['code' => $code, 'name' => "Dealer $code", 'owner_name' => "Owner $code", 'phone' => '01700000000', 'area_id' => $this->area->id],
            100000,
            $email ? ['name' => "Owner $code", 'email' => $email, 'password' => 'secret-123'] : null,
        );
    }

    private function loginAs(User $user): void
    {
        Tenant::flush();
        $this->actingAs($user);
    }

    private function deliverAll(SalesOrder $order): void
    {
        Livewire::test(DeliveryCreate::class, ['salesOrder' => $order->fresh()])->call('save')->assertHasNoErrors();
    }

    public function test_opening_a_dealer_creates_its_account_warehouse_and_login(): void
    {
        Livewire::test(DealersIndex::class)
            ->call('create')
            ->set('code', 'd-01')->set('name', 'Rahman Traders')->set('owner_name', 'Rahman')
            ->set('phone', '01711111111')->set('area_id', $this->area->id)->set('credit_limit', '250000')
            ->set('login_email', 'rahman@dealer.test')->set('login_password', 'secret-123')
            ->call('save')
            ->assertHasNoErrors();

        $dealer = Dealer::where('code', 'D-01')->firstOrFail();
        $account = $dealer->account;
        $this->assertNotNull($account);
        $this->assertNull($account->dealer_id, 'the account is a head office customer');
        $this->assertEquals(250000, (float) $account->credit_limit);
        $this->assertSame(1, $dealer->warehouses()->count());

        $login = User::where('email', 'rahman@dealer.test')->firstOrFail();
        $this->assertSame($dealer->id, $login->dealer_id);
        $this->assertTrue($login->hasRole('Dealer'));

        // Editing the credit limit updates the account.
        Livewire::test(DealersIndex::class)->call('edit', $dealer->id)->set('credit_limit', '300000')->call('save')->assertHasNoErrors();
        $this->assertEquals(300000, (float) $account->fresh()->credit_limit);

        $this->get(route('dealers.show', $dealer))->assertOk()->assertSee('Rahman Traders');
    }

    public function test_a_new_login_never_inherits_a_stale_role_row(): void
    {
        // A role row left behind by a deleted user whose id the next user will get.
        $nextId = (int) User::max('id') + 1;
        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id' => \Spatie\Permission\Models\Role::findByName('Manager')->id,
            'model_type' => User::class,
            'model_id' => $nextId,
        ]);

        $dealer = $this->openDealer('A', 'a@dealer.test');
        $login = User::where('email', 'a@dealer.test')->first();

        $this->assertSame($nextId, $login->id);
        $this->assertSame(['Dealer'], $login->fresh()->getRoleNames()->all());
        $this->assertFalse($login->fresh()->can('dealers.view'));
        $this->assertSame($dealer->id, $login->dealer_id);
    }

    public function test_each_business_sees_only_its_own_records(): void
    {
        $hqShop = $this->retailer(['name' => 'HQ Direct Shop']);
        $a = $this->openDealer('A', 'a@dealer.test');
        $b = $this->openDealer('B', 'b@dealer.test');
        $aShop = $this->retailer(['name' => 'A Shop', 'dealer_id' => $a->id]);
        $this->retailer(['name' => 'B Shop', 'dealer_id' => $b->id]);

        // Head office: its direct customers and the dealers' accounts, never the dealers' customers.
        $names = Retailer::pluck('name')->all();
        $this->assertContains('HQ Direct Shop', $names);
        $this->assertContains('Dealer A', $names);
        $this->assertNotContains('A Shop', $names);
        $this->assertNotContains('B Shop', $names);
        $this->assertSame([$this->hq->id], Warehouse::pluck('id')->all());

        // Dealer A: only its own customers and warehouse.
        $this->loginAs(User::where('email', 'a@dealer.test')->first());
        $this->assertSame(['A Shop'], Retailer::pluck('name')->all());
        $this->assertSame($a->warehouses()->pluck('id')->all(), Warehouse::pluck('id')->all());
        $this->get(route('retailers.show', $hqShop))->assertNotFound();

        // A customer the dealer adds is the dealer's.
        Livewire::test(RetailersIndex::class)
            ->call('create')
            ->set('code', 'A-002')->set('name', 'A Second Shop')->set('phone', '01800000000')
            ->set('form_area_id', $this->area->id)->set('credit_limit', '5000')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame($a->id, Retailer::where('code', 'A-002')->value('dealer_id'));

        // The dealer cannot sell another business's customer from its own store, or use the head office's store.
        $p = $this->product();
        Livewire::test(OrderCreate::class)
            ->set('retailer_id', $aShop->id)->set('warehouse_id', $this->hq->id)
            ->call('addProduct', $p->id)->call('save')
            ->assertHasErrors('warehouse_id');
    }

    public function test_head_office_sale_to_a_dealer_moves_the_same_batches_into_the_dealer_store(): void
    {
        $dealer = $this->openDealer('A', 'a@dealer.test');
        $p = $this->product(['sale_price' => 100, 'type' => 'finished_good']);
        app(InventoryService::class)->receiveStock($p->id, $this->hq->id, 'AGT-2611-01', '2027-11-30', 60, 50, $p);

        $c = Livewire::test(OrderCreate::class)
            ->set('retailer_id', $dealer->account->id)->set('warehouse_id', $this->hq->id)
            ->call('addProduct', $p->id)->set('items.0.quantity', 20)->call('save')
            ->assertHasNoErrors();
        $order = SalesOrder::latest('id')->first();

        Livewire::test(OrderShow::class, ['salesOrder' => $order])
            ->set('discountType', 'percent')->set('discountValue', '10')->call('applyDiscount')
            ->call('approve');
        $this->deliverAll($order);

        $this->assertEquals(30, (float) Stock::where('warehouse_id', $this->hq->id)->where('product_id', $p->id)->value('quantity'));
        $this->assertEquals(1800, (float) $dealer->account->fresh()->current_due);   // 20 × 100 − 10 %

        $dealerStore = $dealer->mainWarehouse();
        $arrived = Tenant::bypass(fn () => Batch::where('warehouse_id', $dealerStore->id)->first());
        $this->assertSame('AGT-2611-01', $arrived->batch_no, 'the batch number travels with the goods');
        $this->assertSame('2027-11-30', \Carbon\Carbon::parse($arrived->expiry_date)->toDateString());
        $this->assertEquals(20, (float) $arrived->quantity_available);
        $this->assertEqualsWithDelta(90, (float) $arrived->cost_price, 0.001, 'the dealer\'s cost is what it paid');

        // The dealer now sells to its own customer from its own store.
        $shop = $this->retailer(['name' => 'A Shop', 'dealer_id' => $dealer->id]);
        $this->loginAs(User::where('email', 'a@dealer.test')->first());

        Livewire::test(OrderCreate::class)
            ->set('retailer_id', $shop->id)->set('warehouse_id', $dealerStore->id)
            ->call('addProduct', $p->id)->set('items.0.quantity', 5)->call('save')
            ->assertHasNoErrors();
        $secondary = SalesOrder::latest('id')->first();
        $this->assertSame($shop->id, $secondary->retailer_id);

        Livewire::test(OrderShow::class, ['salesOrder' => $secondary])->call('approve');
        $this->deliverAll($secondary);

        $this->assertEquals(15, (float) Stock::where('warehouse_id', $dealerStore->id)->where('product_id', $p->id)->value('quantity'));
        $this->assertEquals(500, (float) $shop->fresh()->current_due);

        // The head office does not see the dealer's own sale among its orders.
        $this->loginAs($this->admin);
        $this->assertFalse(SalesOrder::whereKey($secondary->id)->exists());
        $this->get(route('sales.orders.show', $secondary))->assertNotFound();
    }

    public function test_dealer_orders_from_agro_tech_through_the_portal(): void
    {
        $dealer = $this->openDealer('A', 'a@dealer.test');
        $p = $this->product(['sale_price' => 100, 'type' => 'finished_good']);
        $this->receive($p, 40);

        $this->loginAs(User::where('email', 'a@dealer.test')->first());
        Livewire::test(PortalOrderCreate::class)
            ->call('addProduct', $p->id)
            ->set('items.0.quantity', 10)
            ->call('save')
            ->assertHasNoErrors();

        $order = Tenant::bypass(fn () => SalesOrder::latest('id')->first());
        $this->assertSame($dealer->account->id, $order->retailer_id);
        $this->assertSame($this->hq->id, $order->warehouse_id, 'the head office supplies it');

        $this->get(route('portal.orders'))->assertOk()->assertSee($order->number);
        $this->get(route('portal.orders.show', $order))->assertOk();
    }

    public function test_goods_a_dealer_sends_back_leave_the_dealer_stock(): void
    {
        $dealer = $this->openDealer('A');
        $p = $this->product(['sale_price' => 100]);
        $store = $dealer->mainWarehouse();
        Tenant::bypass(fn () => app(InventoryService::class)->receiveStock($p->id, $store->id, 'AGT-1', null, 90, 10, $p));

        $request = ReturnRequest::create([
            'number' => 'RET-1', 'retailer_id' => $dealer->account->id, 'request_date' => today(), 'status' => 'approved',
        ]);
        ReturnRequestItem::create(['return_request_id' => $request->id, 'product_id' => $p->id, 'quantity' => 4, 'unit_price' => 100]);

        $c = Livewire::test(ReturnReceiveCreate::class, ['returnRequest' => $request]);
        $this->assertSame($this->hq->id, (int) $c->get('warehouse_id'));
        $lines = $c->get('lines');
        $lines[0]['batch_no'] = 'AGT-1';
        $c->set('lines', $lines)->call('save')->assertHasNoErrors();

        $this->assertEquals(6, (float) Tenant::bypass(fn () => Stock::where('warehouse_id', $store->id)->where('product_id', $p->id)->value('quantity')));
        $this->assertEquals(4, (float) Stock::where('warehouse_id', $this->hq->id)->where('product_id', $p->id)->value('quantity'));

        // More than the dealer holds: refused with a message, nothing changes.
        $request2 = ReturnRequest::create([
            'number' => 'RET-2', 'retailer_id' => $dealer->account->id, 'request_date' => today(), 'status' => 'approved',
        ]);
        ReturnRequestItem::create(['return_request_id' => $request2->id, 'product_id' => $p->id, 'quantity' => 9, 'unit_price' => 100]);
        $c = Livewire::test(ReturnReceiveCreate::class, ['returnRequest' => $request2]);
        $lines = $c->get('lines');
        $lines[0]['batch_no'] = 'AGT-1';
        $c->set('lines', $lines)->call('save')->assertHasErrors('lines');
        $this->assertSame('approved', $request2->fresh()->status);
    }

    public function test_dealer_sales_report_shows_primary_and_secondary_side_by_side(): void
    {
        $dealer = $this->openDealer('A', 'a@dealer.test');
        $p = $this->product(['sale_price' => 100]);
        $this->receive($p, 50);

        // Primary: head office sells 10 to the dealer.
        Livewire::test(OrderCreate::class)
            ->set('retailer_id', $dealer->account->id)->set('warehouse_id', $this->hq->id)
            ->call('addProduct', $p->id)->set('items.0.quantity', 10)->call('save');
        $order = SalesOrder::latest('id')->first();
        Livewire::test(OrderShow::class, ['salesOrder' => $order])->call('approve');
        $this->deliverAll($order);

        // Secondary: the dealer sells 4 to its customer.
        $shop = $this->retailer(['dealer_id' => $dealer->id]);
        $this->loginAs(User::where('email', 'a@dealer.test')->first());
        Livewire::test(OrderCreate::class)
            ->set('retailer_id', $shop->id)->set('warehouse_id', $dealer->mainWarehouse()->id)
            ->call('addProduct', $p->id)->set('items.0.quantity', 4)->call('save');
        $secondary = SalesOrder::latest('id')->first();
        Livewire::test(OrderShow::class, ['salesOrder' => $secondary])->call('approve');
        $this->deliverAll($secondary);

        $this->get(route('reports.dealers'))->assertForbidden();
        $this->get(route('reports.export.dealers'))->assertForbidden();

        $this->loginAs($this->admin);
        $rows = app(\App\Services\Reports\DealerSalesReport::class)->rows(today()->startOfDay(), today()->endOfDay());
        $row = collect($rows)->firstWhere('name', 'Dealer A');
        $this->assertEquals(1000, $row['bought']);
        $this->assertEquals(1000, $row['owes']);
        $this->assertEquals(400, $row['sold']);
        $this->assertEquals(400, $row['customers_owe']);
        $this->assertEqualsWithDelta(600, $row['stock_value'], 0.01, '6 left at the dealer\'s cost of 100');

        $this->get(route('reports.dealers'))->assertOk()->assertSee('Dealer A');
        $this->get(route('reports.export.dealers'))->assertOk();
        $this->get(route('reports.pdf', 'dealers'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_head_office_staff_placed_with_a_dealer_and_notifications_go_to_the_owner(): void
    {
        Notification::fake();
        $dealer = $this->openDealer('A', 'a@dealer.test');
        $dealerUser = User::where('email', 'a@dealer.test')->first();

        $repUser = $this->userWithRole('Sales Representative');
        $rep = Representative::create(['user_id' => $repUser->id, 'employee_code' => 'HQ-R1', 'name' => 'Head office rep', 'active' => true]);

        Livewire::test(DealersShow::class, ['dealer' => $dealer])
            ->set('place_representative_id', $rep->id)->call('placeRepresentative')->assertHasNoErrors();
        $this->assertTrue($dealer->placedRepresentatives()->whereKey($rep->id)->exists());

        // The dealer sees the placed rep among its staff.
        $this->loginAs($dealerUser);
        $this->assertContains('Head office rep', Representative::pluck('name')->all());

        // The rep works for the dealer's customer assigned to them.
        $shop = Tenant::bypass(fn () => $this->retailer(['name' => 'A Shop', 'dealer_id' => $dealer->id, 'representative_id' => $rep->id]));
        $p = $this->product();
        Tenant::bypass(fn () => app(InventoryService::class)->receiveStock($p->id, $dealer->mainWarehouse()->id, 'B1', null, 50, 10, $p));

        $this->loginAs($repUser);
        $this->assertContains('A Shop', Retailer::pluck('name')->all());
        Livewire::test(OrderCreate::class)
            ->set('retailer_id', $shop->id)->set('warehouse_id', $dealer->mainWarehouse()->id)
            ->call('addProduct', $p->id)->call('save')
            ->assertHasNoErrors();

        // The pending order goes to the dealer's login, not to the head office.
        Notification::assertSentTo($dealerUser, SalesOrderPending::class);
        Notification::assertNotSentTo($this->admin, SalesOrderPending::class);
    }
}
