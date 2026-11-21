<?php

namespace Tests\Feature;

use App\Livewire\Portal\Returns;
use App\Livewire\Portal\Warranty;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Returns\ReturnRequest;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceItem;
use App\Models\Sales\SalesOrder;
use App\Models\Warranty\WarrantyClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Retailer portal: return requests and warranty claims may only be made for
 * the retailer's own purchases, within what was bought, at the invoiced price.
 */
class PortalReturnsWarrantyTest extends TestCase
{
    use RefreshDatabase;

    private Retailer $mine;
    private Retailer $other;
    private Product $bought;
    private Product $notBought;
    private SalesInvoice $myInvoice;
    private SalesInvoice $othersInvoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $me = $this->userWithRole('Retailer');
        $this->mine = $this->retailer(['user_id' => $me->id]);
        $this->other = $this->retailer();

        $this->bought = $this->product(['sale_price' => 120]);
        $this->notBought = $this->product();

        // I bought 10 at 95 (a discounted price, different from the master price).
        $this->myInvoice = $this->invoice($this->mine, $this->bought, 10, 95, 'INV-MINE');
        $this->othersInvoice = $this->invoice($this->other, $this->notBought, 5, 100, 'INV-OTHER');

        $this->actingAs($me);
    }

    private function invoice(Retailer $retailer, Product $product, float $qty, float $price, string $number): SalesInvoice
    {
        $order = SalesOrder::create([
            'number' => 'SO-'.$number, 'order_type' => 'retailer', 'retailer_id' => $retailer->id,
            'warehouse_id' => $this->warehouse()->id, 'status' => 'completed', 'order_date' => today(),
            'subtotal' => $qty * $price, 'discount' => 0, 'grand_total' => $qty * $price,
        ]);
        $invoice = SalesInvoice::create([
            'number' => $number, 'sales_order_id' => $order->id, 'retailer_id' => $retailer->id,
            'invoice_date' => today(), 'subtotal' => $qty * $price, 'discount' => 0, 'grand_total' => $qty * $price,
            'paid_amount' => 0, 'due_amount' => $qty * $price, 'status' => 'unpaid',
        ]);
        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id, 'product_id' => $product->id,
            'quantity' => $qty, 'unit_price' => $price, 'line_total' => $qty * $price,
        ]);

        return $invoice;
    }

    public function test_return_request_uses_invoiced_price_and_belongs_to_me(): void
    {
        Livewire::test(Returns::class)
            ->call('openForm')
            ->set('items.0.product_id', $this->bought->id)
            ->set('items.0.quantity', 4)
            ->call('save')
            ->assertHasNoErrors();

        $request = ReturnRequest::with('items')->sole();
        $this->assertSame($this->mine->id, $request->retailer_id);
        $this->assertSame('pending', $request->status);
        $this->assertSame(95.0, (float) $request->items[0]->unit_price);
    }

    public function test_cannot_return_more_than_bought_including_open_requests(): void
    {
        $form = fn ($qty) => Livewire::test(Returns::class)->call('openForm')
            ->set('items.0.product_id', $this->bought->id)->set('items.0.quantity', $qty)->call('save');

        $form(11)->assertHasErrors('items.0.quantity');
        $form(7)->assertHasNoErrors();
        $form(4)->assertHasErrors('items.0.quantity');   // only 3 left
        $form(3)->assertHasNoErrors();

        $this->assertSame(2, ReturnRequest::count());
    }

    public function test_cannot_return_product_i_did_not_buy(): void
    {
        Livewire::test(Returns::class)->call('openForm')
            ->set('items.0.product_id', $this->notBought->id)->set('items.0.quantity', 1)->call('save')
            ->assertHasErrors('items.0.product_id');

        $this->assertSame(0, ReturnRequest::count());
    }

    public function test_return_list_shows_only_my_requests(): void
    {
        ReturnRequest::create(['number' => 'RET-OTHER', 'retailer_id' => $this->other->id, 'request_date' => today(), 'status' => 'pending']);

        Livewire::test(Returns::class)->assertDontSee('RET-OTHER');
    }

    public function test_warranty_claim_for_bought_product(): void
    {
        Livewire::test(Warranty::class)
            ->call('openForm')
            ->set('product_id', $this->bought->id)
            ->set('sales_invoice_id', $this->myInvoice->id)
            ->set('issue_description', 'Stopped working after a week')
            ->call('save')
            ->assertHasNoErrors();

        $claim = WarrantyClaim::sole();
        $this->assertSame($this->mine->id, $claim->retailer_id);
        $this->assertSame($this->myInvoice->id, $claim->sales_invoice_id);
    }

    public function test_warranty_claim_rejects_other_products_and_other_retailers_invoices(): void
    {
        Livewire::test(Warranty::class)->call('openForm')
            ->set('product_id', $this->notBought->id)->set('issue_description', 'Broken item')->call('save')
            ->assertHasErrors('product_id');

        Livewire::test(Warranty::class)->call('openForm')
            ->set('product_id', $this->bought->id)->set('sales_invoice_id', $this->othersInvoice->id)
            ->set('issue_description', 'Broken item')->call('save')
            ->assertHasErrors('sales_invoice_id');

        $this->assertSame(0, WarrantyClaim::count());
    }

    public function test_staff_cannot_open_portal_pages(): void
    {
        $this->actingAs($this->userWithRole('Sales Representative'));

        $this->get(route('portal.returns'))->assertForbidden();
        $this->get(route('portal.warranty'))->assertForbidden();
    }
}
