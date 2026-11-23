<?php

namespace Tests\Feature;

use App\Livewire\Sales\Orders\Create as SalesOrderForm;
use App\Livewire\Sales\Orders\Index as SalesOrderIndex;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Row actions on the lists: view, edit, delete/cancel, print. */
class ActionButtonsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->admin = $this->userWithRole('Admin');
        $this->actingAs($this->admin);
    }

    private function salesOrder(string $status = 'pending'): SalesOrder
    {
        $p = $this->product(['type' => 'finished_good']);
        $r = $this->retailer(['credit_limit' => 1000000]);
        $o = SalesOrder::create([
            'number' => 'SO-'.uniqid(), 'order_type' => 'retailer', 'retailer_id' => $r->id,
            'warehouse_id' => $this->warehouse()->id, 'status' => $status, 'order_date' => today(),
            'subtotal' => 500, 'discount' => 0, 'grand_total' => 500,
        ]);
        SalesOrderItem::create(['sales_order_id' => $o->id, 'product_id' => $p->id, 'quantity' => 5, 'unit_price' => 100]);

        return $o;
    }

    public function test_a_pending_sales_order_can_be_edited(): void
    {
        $o = $this->salesOrder();
        $this->get(route('sales.orders.edit', $o))->assertOk()->assertSee('Update sales order');

        Livewire::test(SalesOrderForm::class, ['salesOrder' => $o])
            ->assertSet('editingId', $o->id)
            ->set('items.0.quantity', 8)
            ->call('save')
            ->assertRedirect(route('sales.orders.show', $o));

        $o->refresh();
        $this->assertSame(800.0, (float) $o->grand_total);
        $this->assertSame(1, $o->items()->count());
        $this->assertSame(1, SalesOrder::count());
    }

    public function test_an_approved_sales_order_cannot_be_edited_or_deleted(): void
    {
        $o = $this->salesOrder('approved');
        $this->get(route('sales.orders.edit', $o))->assertForbidden();

        Livewire::test(SalesOrderIndex::class)->call('delete', $o->id);
        $this->assertNotNull($o->fresh());
    }

    public function test_a_pending_sales_order_can_be_deleted(): void
    {
        $o = $this->salesOrder();
        Livewire::test(SalesOrderIndex::class)
            ->assertSee(route('sales.orders.edit', $o))
            ->assertSee(route('documents.print', ['sales-order', $o->id]))
            ->call('delete', $o->id);

        $this->assertNull($o->fresh());
        $this->assertSame(0, SalesOrderItem::count());
    }

    public function test_documents_print_as_pdf_with_permission_checks(): void
    {
        $o = $this->salesOrder();
        $res = $this->get(route('documents.print', ['sales-order', $o->id]))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));

        $this->get(route('documents.print', ['nonsense', $o->id]))->assertNotFound();
        $this->get(route('documents.print', ['sales-order', 999999]))->assertNotFound();

        $this->actingAs($this->userWithRole('HR Manager'));
        $this->get(route('documents.print', ['sales-order', $o->id]))->assertForbidden();
    }

    private function bal(string $key): float
    {
        $id = \App\Models\Accounts\Account::bySystemKey($key)->id;

        return round((float) \App\Models\Accounts\JournalLine::where('account_id', $id)->sum('debit') - (float) \App\Models\Accounts\JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    /** Sell 3 at 100 and collect 200 through the real screens. */
    private function sellAndCollect(): array
    {
        $p = $this->product(['sale_price' => 100, 'purchase_price' => 70, 'type' => 'finished_good']);
        $this->receive($p, 10, 'B1', 70);
        $r = $this->retailer(['credit_limit' => 100000]);
        Livewire::test(SalesOrderForm::class)
            ->set('retailer_id', $r->id)->set('warehouse_id', $this->warehouse()->id)
            ->call('addProduct', $p->id)->set('items.0.quantity', 3)->call('save')->assertHasNoErrors();
        $order = SalesOrder::latest('id')->first();
        Livewire::test(\App\Livewire\Sales\Orders\Show::class, ['salesOrder' => $order])->call('approve');
        Livewire::test(\App\Livewire\Delivery\Create::class, ['salesOrder' => $order->fresh()])->call('save')->assertHasNoErrors();
        Livewire::test(\App\Livewire\Collection\Create::class)
            ->set('retailer_id', $r->id)->set('amount', '200')->set('method', 'cash')->call('save')->assertHasNoErrors();

        return [$p, $r->fresh(), $order->fresh()];
    }

    public function test_voiding_a_collection_restores_the_due_and_reverses_the_voucher(): void
    {
        [, $r, $order] = $this->sellAndCollect();
        $this->assertSame(100.0, (float) $r->current_due);
        $cashBefore = $this->bal('cash');
        $collection = \App\Models\PaymentCollection::first();

        Livewire::test(\App\Livewire\Collection\Index::class)
            ->call('void', $collection->id, 'Cheque bounced')
            ->assertSee('voided');

        $this->assertNull($collection->fresh());
        $this->assertSame(300.0, (float) $r->fresh()->current_due);
        $this->assertSame(0.0, (float) $order->invoices()->sum('paid_amount'));
        $this->assertSame(300.0, $this->bal('ar'));
        $this->assertSame(round($cashBefore - 200, 2), $this->bal('cash'));
        $this->assertTrue(\App\Models\Settings\AuditLog::where('action', 'collection.voided')->exists());

        // Only finance can void.
        $this->actingAs($this->userWithRole('Sales Representative'));
        Livewire::test(\App\Livewire\Collection\Index::class)->call('void', 1, 'x')->assertForbidden();
    }

    public function test_voiding_a_stock_adjustment_undoes_stock_and_books(): void
    {
        $p = $this->product(['purchase_price' => 50]);
        $w = $this->warehouse();
        $adj = app(\App\Services\InventoryService::class)->adjustStock($w->id, $p->id, null, 4, 'Found in count', $this->admin->id, today()->toDateString());
        $this->assertSame(200.0, $this->bal('inventory'));

        Livewire::test(\App\Livewire\Inventory\StockAdjustments::class)->call('void', $adj->id, 'Counted twice');

        $this->assertSame(0.0, (float) \App\Models\Stock::where('product_id', $p->id)->value('quantity'));
        $this->assertSame(0.0, $this->bal('inventory'));
        $this->assertSame(0.0, $this->bal('stock_gain'));
        $this->assertSame(0.0, $this->bal('stock_loss'));

        // Not twice.
        Livewire::test(\App\Livewire\Inventory\StockAdjustments::class)->call('void', $adj->id, 'again')->assertSee('already been voided');
    }

    private function receivedOrder(float $qty = 10): \App\Models\Purchase\PurchaseOrder
    {
        $p = $this->product(['type' => 'trading', 'purchase_price' => 20]);
        $company = \App\Models\Company::first();
        Livewire::test(\App\Livewire\Purchase\Orders\Create::class)
            ->set('company_id', $company->id)->set('warehouse_id', $this->warehouse()->id)
            ->set('items', [['product_id' => $p->id, 'quantity' => $qty, 'unit_price' => 20]])
            ->call('save')->assertHasNoErrors();
        $po = \App\Models\Purchase\PurchaseOrder::latest('id')->first();
        $po->update(['status' => 'sent']);
        $c = Livewire::test(\App\Livewire\Purchase\Receipts\Create::class, ['purchaseOrder' => $po]);
        $lines = $c->get('lines');
        $lines[0]['quantity'] = $qty;
        $lines[0]['batch_no'] = 'GRN-B1';
        $c->set('lines', $lines)->call('save')->assertHasNoErrors();

        return $po->fresh();
    }

    public function test_a_purchase_order_can_be_edited_and_deleted_until_received(): void
    {
        $p = $this->product(['type' => 'trading']);
        Livewire::test(\App\Livewire\Purchase\Orders\Create::class)
            ->set('company_id', \App\Models\Company::first()->id)->set('warehouse_id', $this->warehouse()->id)
            ->set('items', [['product_id' => $p->id, 'quantity' => 5, 'unit_price' => 10]])
            ->call('save');
        $po = \App\Models\Purchase\PurchaseOrder::first();

        Livewire::test(\App\Livewire\Purchase\Orders\Create::class, ['purchaseOrder' => $po])
            ->set('items.0.quantity', 7)->call('save')->assertHasNoErrors();
        $this->assertSame(70.0, (float) $po->fresh()->total_amount);
        $this->get(route('documents.print', ['purchase-order', $po->id]))->assertOk();

        Livewire::test(\App\Livewire\Purchase\Orders\Index::class)->call('delete', $po->id);
        $this->assertNull($po->fresh());

        $received = $this->receivedOrder();
        $this->get(route('purchase.orders.edit', $received))->assertForbidden();
        Livewire::test(\App\Livewire\Purchase\Orders\Index::class)->call('delete', $received->id);
        $this->assertNotNull($received->fresh());
    }

    public function test_voiding_a_goods_receipt_takes_the_stock_out_and_reopens_the_order(): void
    {
        $po = $this->receivedOrder(10);
        $receipt = $po->goodsReceipts()->first();
        $this->assertSame('completed', $po->status);
        $this->assertSame(-200.0, $this->bal('ap'));
        $this->get(route('documents.print', ['goods-receipt', $receipt->id]))->assertOk();

        Livewire::test(\App\Livewire\Purchase\Receipts\Index::class)->call('void', $receipt->id, 'Wrong supplier');

        $this->assertNull($receipt->fresh());
        $po->refresh();
        $this->assertSame('sent', $po->status);
        $this->assertSame(0.0, (float) $po->items()->sum('received_quantity'));
        $this->assertSame(0.0, (float) \App\Models\Stock::sum('quantity'));
        $this->assertSame(0.0, $this->bal('ap'));
        $this->assertSame(0.0, $this->bal('inventory'));
    }

    public function test_a_goods_receipt_whose_stock_was_used_cannot_be_voided(): void
    {
        $po = $this->receivedOrder(10);
        $receipt = $po->goodsReceipts()->first();
        $batch = \App\Models\Batch::where('batch_no', 'GRN-B1')->first();
        app(\App\Services\InventoryService::class)->takeFromBatch($batch->id, 1, $receipt);

        Livewire::test(\App\Livewire\Purchase\Receipts\Index::class)->call('void', $receipt->id, 'x')
            ->assertSee('already been sold, moved or used');
        $this->assertNotNull($receipt->fresh());
    }

    public function test_deleting_an_advance_reverses_its_voucher(): void
    {
        $rep = \App\Models\Representative::create(['employee_code' => 'R1', 'name' => 'Rep', 'active' => true]);
        Livewire::test(\App\Livewire\Payroll\Advances\Index::class)
            ->set('form_representative_id', $rep->id)->set('amount', '500')->call('save')->assertHasNoErrors();
        $this->assertNotSame(0.0, $this->bal('cash'));

        Livewire::test(\App\Livewire\Payroll\Advances\Index::class)->call('delete', \App\Models\Payroll\Advance::first()->id);
        $this->assertSame(0, \App\Models\Payroll\Advance::count());
        $this->assertSame(0.0, $this->bal('cash'));
    }

    public function test_a_used_master_record_gets_a_message_instead_of_an_error(): void
    {
        $o = $this->salesOrder();
        $productId = $o->items()->value('product_id');

        Livewire::test(\App\Livewire\MasterData\Products::class)->call('delete', $productId)
            ->assertSee('already used in transactions');
        $this->assertNotNull(\App\Models\Product::find($productId));
    }

    public function test_every_document_type_prints(): void
    {
        $o = $this->salesOrder();
        $this->get(route('documents.print', ['sales-order', $o->id]))->assertOk();
        $po = $this->receivedOrder();
        $this->get(route('documents.print', ['goods-receipt', $po->goodsReceipts()->first()->id]))->assertOk();

        [, , $order] = $this->sellAndCollect();
        $this->get(route('documents.print', ['delivery-note', $order->deliveryNotes()->first()->id]))->assertOk();

        // Bangla PDFs go through mPDF.
        $this->admin->update(['locale' => 'bn']);
        $res = $this->get(route('documents.print', ['delivery-note', $order->deliveryNotes()->first()->id]))->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->admin->update(['locale' => 'en']);

        $this->get(route('purchase.receipts.index'))->assertOk()->assertSee(route('documents.print', ['goods-receipt', $po->goodsReceipts()->first()->id]));
        $this->get(route('delivery.index'))->assertOk()->assertSee('Print challan');
        foreach (['returns.index', 'warranty.index', 'purchase.returns.index', 'returns.company.index', 'damage.index', 'inventory.transfers', 'inventory.adjustments', 'accounts.vouchers.index', 'collection.index', 'hr.leaves', 'payroll.advances.index'] as $r) {
            $this->get(route($r))->assertOk();
        }
    }

    public function test_pending_return_requests_and_warranty_claims_can_be_edited(): void
    {
        $p = $this->product();
        $r = $this->retailer();
        Livewire::test(\App\Livewire\Returns\Requests\Create::class)
            ->set('retailer_id', $r->id)->set('items', [['product_id' => $p->id, 'quantity' => 2, 'unit_price' => '100', 'reason' => '']])
            ->call('save')->assertHasNoErrors();
        $req = \App\Models\Returns\ReturnRequest::first();

        Livewire::test(\App\Livewire\Returns\Requests\Create::class, ['returnRequest' => $req])
            ->assertSet('editingId', $req->id)->set('items.0.quantity', 5)->call('save')->assertHasNoErrors();
        $this->assertSame(5.0, (float) $req->items()->value('quantity'));
        $this->assertSame(1, \App\Models\Returns\ReturnRequest::count());

        $req->update(['status' => 'approved']);
        $this->get(route('returns.edit', $req))->assertForbidden();

        Livewire::test(\App\Livewire\Warranty\Claims\Create::class)
            ->set('retailer_id', $r->id)->set('product_id', $p->id)->set('issue_description', 'Leaks')->call('save')->assertHasNoErrors();
        $claim = \App\Models\Warranty\WarrantyClaim::first();
        Livewire::test(\App\Livewire\Warranty\Claims\Create::class, ['warrantyClaim' => $claim])
            ->set('issue_description', 'Leaks at the valve')->call('save')->assertHasNoErrors();
        $this->assertSame('Leaks at the valve', $claim->fresh()->issue_description);
        $this->assertSame(1, \App\Models\Warranty\WarrantyClaim::count());
    }

    public function test_voiding_a_transfer_puts_the_stock_back(): void
    {
        $p = $this->product();
        $a = $this->warehouse();
        $b = \App\Models\Warehouse::create(['name' => 'Second', 'type' => 'branch', 'active' => true]);
        $this->receive($p, 10, 'T1');
        $batch = \App\Models\Batch::where('batch_no', 'T1')->first();
        $t = app(\App\Services\Production\StockTransferService::class)->create($a->id, $b->id, today()->toDateString(), [['batch_id' => $batch->id, 'quantity' => 4]]);
        $this->assertSame(6.0, (float) $batch->fresh()->quantity_available);

        Livewire::test(\App\Livewire\Production\Transfers::class)->call('void', $t->id, 'Wrong store');

        $this->assertNull($t->fresh());
        $this->assertSame(10.0, (float) $batch->fresh()->quantity_available);
        $this->assertSame(10.0, (float) \App\Models\Stock::where('warehouse_id', $a->id)->value('quantity'));
        $this->assertSame(0.0, (float) \App\Models\Stock::where('warehouse_id', $b->id)->value('quantity'));
    }

    public function test_an_approved_leave_can_be_cancelled_until_the_salary_sheet_is_approved(): void
    {
        $e = \App\Models\Hr\Employee::create(['employee_code' => 'L-1', 'name' => 'Worker', 'join_date' => '2025-01-01', 'category' => 'factory', 'basic' => 9000]);
        $type = \App\Models\Hr\LeaveType::query()->value('id');
        $leave = \App\Models\Hr\LeaveApplication::create(['employee_id' => $e->id, 'leave_type_id' => $type, 'from_date' => '2026-10-05', 'to_date' => '2026-10-06', 'days' => 2, 'status' => 'approved']);
        $other = \App\Models\Hr\LeaveApplication::create(['employee_id' => $e->id, 'leave_type_id' => $type, 'from_date' => '2026-09-05', 'to_date' => '2026-09-05', 'days' => 1, 'status' => 'approved']);
        \App\Models\Hr\PayrollRun::create(['number' => 'SAL-1', 'period' => '2026-09', 'status' => 'approved']);

        Livewire::test(\App\Livewire\Hr\Leaves::class)->call('cancelLeave', $leave->id);
        $this->assertSame('cancelled', $leave->fresh()->status);

        Livewire::test(\App\Livewire\Hr\Leaves::class)->call('cancelLeave', $other->id)->assertSee('already approved');
        $this->assertSame('approved', $other->fresh()->status);
    }

    public function test_a_scanned_barcode_or_sku_adds_the_product(): void
    {
        $p = $this->product(['type' => 'finished_good', 'barcode' => '8901234567890', 'sku' => 'SCAN-1']);

        $c = Livewire::test(SalesOrderForm::class)->call('addByCode', '8901234567890')->assertSet('items.0.product_id', $p->id);
        $c->call('addByCode', 'SCAN-1')->assertSet('items.0.quantity', 2.0);
        $c->call('addByCode', 'nope')->assertSet('scanMessage', 'No product with the code nope.');
        $this->get(route('sales.orders.create'))->assertOk()->assertSee('Scan barcode with the camera');
    }
}