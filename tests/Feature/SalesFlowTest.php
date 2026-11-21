<?php

namespace Tests\Feature;

use App\Livewire\Collection\Create as CollectionCreate;
use App\Livewire\Delivery\Create as DeliveryCreate;
use App\Livewire\Sales\Orders\Create as OrderCreate;
use App\Livewire\Sales\Orders\Show as OrderShow;
use App\Models\Delivery\DeliveryInvoice;
use App\Models\RetailerLedger;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The money path: order → (discount) → approve → partial deliveries →
 * invoices / retailer due / ledger → payment.
 */
class SalesFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->userWithRole('Admin'));
    }

    private function placeOrder($retailer, $product, float $qty): SalesOrder
    {
        $c = Livewire::test(OrderCreate::class)
            ->set('retailer_id', $retailer->id)
            ->set('warehouse_id', $this->warehouse()->id)
            ->call('addProduct', $product->id)
            ->set('items.0.quantity', $qty)
            ->call('save');

        $c->assertHasNoErrors();

        return SalesOrder::latest('id')->first();
    }

    private function deliver(SalesOrder $order, float $qty): void
    {
        $c = Livewire::test(DeliveryCreate::class, ['salesOrder' => $order->fresh()]);
        $lines = $c->get('lines');
        $lines[0]['quantity'] = $qty;
        $c->set('lines', $lines)->call('save')->assertHasNoErrors();
    }

    public function test_order_uses_master_price_and_is_pending(): void
    {
        $p = $this->product(['sale_price' => 120]);
        $order = $this->placeOrder($this->retailer(), $p, 3);

        $this->assertSame('pending', $order->status);
        $this->assertSame(360.0, (float) $order->grand_total);
    }

    public function test_order_over_credit_limit_goes_to_credit_hold(): void
    {
        $r = $this->retailer(['credit_limit' => 1000, 'current_due' => 900]);
        $order = $this->placeOrder($r, $this->product(['sale_price' => 100]), 2);

        $this->assertSame('credit_hold', $order->status);
    }

    public function test_discount_is_shared_across_deliveries_and_adds_up_exactly(): void
    {
        $p = $this->product(['sale_price' => 33.33]);
        $this->receive($p, 100);
        $r = $this->retailer();
        $order = $this->placeOrder($r, $p, 10);   // subtotal 333.30

        Livewire::test(OrderShow::class, ['salesOrder' => $order])
            ->set('discountType', 'percent')->set('discountValue', '7')->call('applyDiscount')
            ->assertHasNoErrors()
            ->call('approve');

        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertSame(23.33, (float) $order->discount);

        $this->deliver($order, 3);
        $this->deliver($order, 3);
        $this->deliver($order, 4);

        $invoices = SalesInvoice::where('sales_order_id', $order->id)->get();
        $this->assertCount(3, $invoices);
        $this->assertEqualsWithDelta(23.33, (float) $invoices->sum('discount'), 0.001);
        $this->assertEqualsWithDelta((float) $order->grand_total, (float) $invoices->sum('grand_total'), 0.001);

        // delivery invoices mirror the sales invoices
        $this->assertEqualsWithDelta(23.33, (float) DeliveryInvoice::where('sales_order_id', $order->id)->sum('discount'), 0.001);

        // retailer owes the net amount, and the ledger agrees
        $this->assertEqualsWithDelta((float) $order->grand_total, (float) $r->fresh()->current_due, 0.001);
        $this->assertEqualsWithDelta((float) $order->grand_total, (float) RetailerLedger::where('retailer_id', $r->id)->sum('debit'), 0.001);
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_discount_cannot_exceed_subtotal_or_change_after_approval(): void
    {
        $order = $this->placeOrder($this->retailer(), $this->product(['sale_price' => 100]), 1);

        Livewire::test(OrderShow::class, ['salesOrder' => $order])
            ->set('discountValue', '101')->call('applyDiscount')
            ->assertHasErrors('discountValue');

        Livewire::test(OrderShow::class, ['salesOrder' => $order])->call('approve');

        Livewire::test(OrderShow::class, ['salesOrder' => $order->fresh()])
            ->set('discountValue', '10')->call('applyDiscount')
            ->assertHasErrors('discountValue');
    }

    public function test_cannot_deliver_more_than_stock(): void
    {
        $p = $this->product();
        $this->receive($p, 2);
        $order = $this->placeOrder($this->retailer(), $p, 5);
        Livewire::test(OrderShow::class, ['salesOrder' => $order])->call('approve');

        $c = Livewire::test(DeliveryCreate::class, ['salesOrder' => $order->fresh()]);
        $lines = $c->get('lines');
        $lines[0]['quantity'] = 3;
        $c->set('lines', $lines)->call('save')->assertHasErrors();

        $this->assertSame(0, SalesInvoice::count());
    }

    public function test_payment_settles_oldest_invoice_first(): void
    {
        $p = $this->product(['sale_price' => 100]);
        $this->receive($p, 10);
        $r = $this->retailer();
        $order = $this->placeOrder($r, $p, 4);
        Livewire::test(OrderShow::class, ['salesOrder' => $order])->call('approve');
        $this->deliver($order, 2);
        $this->travel(1)->minutes();
        $this->deliver($order, 2);

        Livewire::test(CollectionCreate::class)
            ->set('retailer_id', $r->id)->set('amount', '250')->set('method', 'cash')
            ->call('save')->assertHasNoErrors();

        $invoices = SalesInvoice::orderBy('id')->get();
        $this->assertSame('paid', $invoices[0]->status);
        $this->assertSame('partial', $invoices[1]->status);
        $this->assertSame(150.0, (float) $invoices[1]->due_amount);
        $this->assertSame(150.0, (float) $r->fresh()->current_due);
    }

    public function test_payment_cannot_exceed_due(): void
    {
        $r = $this->retailer(['current_due' => 50]);

        Livewire::test(CollectionCreate::class)
            ->set('retailer_id', $r->id)->set('amount', '60')
            ->call('save')->assertHasErrors('amount');
    }
}
