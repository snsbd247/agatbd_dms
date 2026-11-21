<?php

namespace Tests\Feature;

use App\Livewire\GlobalSearch;
use App\Livewire\Portal\OrderCreate;
use App\Livewire\Portal\Orders;
use App\Livewire\Portal\OrderShow;
use App\Models\Sales\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A retailer must only ever see and touch their own records.
 */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    private $me;
    private $mine;
    private SalesOrder $othersOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->warehouse();

        $this->me = $this->userWithRole('Retailer');
        $this->mine = $this->retailer(['user_id' => $this->me->id]);
        $other = $this->retailer();

        $this->othersOrder = SalesOrder::create([
            'number' => 'SO-OTHER', 'order_type' => 'retailer', 'retailer_id' => $other->id,
            'warehouse_id' => $this->warehouse()->id, 'status' => 'pending', 'order_date' => today(),
            'subtotal' => 10, 'discount' => 0, 'grand_total' => 10,
        ]);

        $this->actingAs($this->me);
    }

    public function test_cannot_open_another_retailers_order(): void
    {
        Livewire::test(OrderShow::class, ['salesOrder' => $this->othersOrder])->assertStatus(404);
    }

    public function test_order_list_and_search_show_only_own_orders(): void
    {
        Livewire::test(Orders::class)->assertDontSee('SO-OTHER');
        Livewire::test(GlobalSearch::class)->set('q', 'SO-')->assertDontSee('SO-OTHER');
    }

    public function test_placed_order_belongs_to_me_and_uses_master_price(): void
    {
        $p = $this->product(['sale_price' => 45]);

        Livewire::test(OrderCreate::class)
            ->call('addProduct', $p->id)
            ->call('changeQuantity', 0, 1)
            ->call('save')
            ->assertHasNoErrors();

        $order = SalesOrder::latest('id')->first();
        $this->assertSame($this->mine->id, $order->retailer_id);
        $this->assertSame(90.0, (float) $order->grand_total);
        $this->assertSame(45.0, (float) $order->items->first()->unit_price);
    }

    public function test_can_cancel_own_pending_order_only(): void
    {
        $own = SalesOrder::create([
            'number' => 'SO-MINE', 'order_type' => 'retailer', 'retailer_id' => $this->mine->id,
            'warehouse_id' => $this->warehouse()->id, 'status' => 'approved', 'order_date' => today(),
            'subtotal' => 10, 'discount' => 0, 'grand_total' => 10,
        ]);

        Livewire::test(OrderShow::class, ['salesOrder' => $own])->call('cancel')->assertHasErrors('cancel');
        $this->assertSame('approved', $own->fresh()->status);

        $own->update(['status' => 'pending']);
        Livewire::test(OrderShow::class, ['salesOrder' => $own->fresh()])->call('cancel');
        $this->assertSame('cancelled', $own->fresh()->status);
    }

    public function test_retailer_cannot_reach_admin_pages(): void
    {
        foreach (['sales.orders.index', 'retailers.index', 'settings.users', 'more.backup-restore', 'payroll.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }

        $this->get(route('portal.orders'))->assertOk();
    }
}
