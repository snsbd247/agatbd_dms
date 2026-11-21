<?php

namespace Tests\Feature;

use App\Livewire\Payroll\Entries\Create as PayrollCreate;
use App\Livewire\Purchase\Orders\Create as PurchaseOrderCreate;
use App\Livewire\Purchase\Orders\Show as PurchaseOrderShow;
use App\Livewire\Purchase\Receipts\Create as GoodsReceiptCreate;
use App\Livewire\Purchase\Returns\Create as PurchaseReturnCreate;
use App\Livewire\Purchase\Returns\Show as PurchaseReturnShow;
use App\Livewire\Returns\Receives\Create as ReturnReceiveCreate;
use App\Livewire\Returns\Requests\Show as ReturnRequestShow;
use App\Livewire\Warranty\Claims\Show as WarrantyClaimShow;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Payroll\Advance;
use App\Models\Payroll\Bonus;
use App\Models\Payroll\PayrollEntry;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Representative;
use App\Models\Returns\CompanyReturn;
use App\Models\Returns\ReturnRequest;
use App\Models\Returns\ReturnRequestItem;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Models\Stock;
use App\Models\StockLedger;
use App\Models\Warranty\WarrantyClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Purchase, returns, warranty, payroll and report pages: the stock and money
 * effects of each flow.
 */
class BusinessFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->warehouse();
        $this->actingAs($this->userWithRole('Dealer'));
    }

    private function stockOf($product): float
    {
        return (float) Stock::where('product_id', $product->id)->sum('quantity');
    }

    // ---- Purchase ------------------------------------------------------

    public function test_purchase_order_partial_receipt_then_complete(): void
    {
        $p = $this->product();
        $company = Company::firstOrCreate(['code' => 'CMP'], ['name' => 'Test Company']);

        Livewire::test(PurchaseOrderCreate::class)
            ->set('company_id', $company->id)
            ->set('warehouse_id', $this->warehouse()->id)
            ->set('items', [['product_id' => $p->id, 'quantity' => 10, 'unit_price' => 60]])
            ->call('save')
            ->assertHasNoErrors();

        $po = PurchaseOrder::sole();
        $this->assertSame('draft', $po->status);

        Livewire::test(PurchaseOrderShow::class, ['purchaseOrder' => $po])->call('send');
        $po->refresh();
        $this->assertSame('sent', $po->status);

        // Cannot receive more than ordered.
        $c = Livewire::test(GoodsReceiptCreate::class, ['purchaseOrder' => $po]);
        $lines = $c->get('lines');
        $lines[0]['quantity'] = 11;
        $lines[0]['batch_no'] = 'PB-1';
        $c->set('lines', $lines)->call('save')->assertHasErrors('lines.0.quantity');
        $this->assertSame(0.0, $this->stockOf($p));

        // Receive 4 now …
        $lines[0]['quantity'] = 4;
        Livewire::test(GoodsReceiptCreate::class, ['purchaseOrder' => $po])->set('lines', $lines)->call('save')->assertHasNoErrors();
        $this->assertSame('partial', $po->fresh()->status);
        $this->assertSame(4.0, $this->stockOf($p));

        // … and the remaining 6 later.
        $c = Livewire::test(GoodsReceiptCreate::class, ['purchaseOrder' => $po->fresh()]);
        $lines = $c->get('lines');
        $this->assertSame(6.0, (float) $lines[0]['quantity']);
        $lines[0]['batch_no'] = 'PB-2';
        $c->set('lines', $lines)->call('save')->assertHasNoErrors();

        $this->assertSame('completed', $po->fresh()->status);
        $this->assertSame(10.0, $this->stockOf($p));
        $this->assertSame(2, Batch::where('product_id', $p->id)->count());
    }

    public function test_purchase_return_takes_stock_out_only_when_sent(): void
    {
        $p = $this->product();
        $this->receive($p, 20, 'RB-1');
        $batch = Batch::where('product_id', $p->id)->sole();

        Livewire::test(PurchaseReturnCreate::class)
            ->set('company_id', $p->company_id)
            ->set('warehouse_id', $this->warehouse()->id)
            ->set('items.0.product_id', $p->id)
            ->set('items.0.batch_id', $batch->id)
            ->set('items.0.quantity', 5)
            ->call('save')
            ->assertHasNoErrors();

        $return = PurchaseReturn::sole();
        $this->assertSame(20.0, $this->stockOf($p), 'a draft return must not move stock');

        Livewire::test(PurchaseReturnShow::class, ['purchaseReturn' => $return])->call('send');
        $this->assertSame('sent', $return->fresh()->status);
        $this->assertSame(15.0, $this->stockOf($p));

        // Sending twice must not take stock out twice.
        Livewire::test(PurchaseReturnShow::class, ['purchaseReturn' => $return->fresh()])->call('send');
        $this->assertSame(15.0, $this->stockOf($p));
    }

    // ---- Retailer returns ----------------------------------------------

    private function returnRequest($retailer, $product, float $qty, float $price): ReturnRequest
    {
        $request = ReturnRequest::create(['number' => 'RET-'.uniqid(), 'retailer_id' => $retailer->id, 'request_date' => today(), 'status' => 'pending']);
        ReturnRequestItem::create(['return_request_id' => $request->id, 'product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $price]);

        return $request;
    }

    public function test_return_receive_restocks_and_credits_retailer(): void
    {
        $p = $this->product();
        $r = $this->retailer(['current_due' => 1000]);
        $request = $this->returnRequest($r, $p, 3, 100);

        // Not receivable before approval.
        Livewire::test(ReturnReceiveCreate::class, ['returnRequest' => $request])->assertForbidden();

        Livewire::test(ReturnRequestShow::class, ['returnRequest' => $request])->call('approve');
        $this->assertSame('approved', $request->fresh()->status);

        $c = Livewire::test(ReturnReceiveCreate::class, ['returnRequest' => $request->fresh()]);
        $lines = $c->get('lines');
        $c->set('lines', $lines)->call('save')->assertHasErrors('lines.0.batch_no');   // restock needs a batch

        $lines[0]['batch_no'] = 'RET-B1';
        Livewire::test(ReturnReceiveCreate::class, ['returnRequest' => $request->fresh()])->set('lines', $lines)->call('save')->assertHasNoErrors();

        $this->assertSame('received', $request->fresh()->status);
        $this->assertSame(3.0, $this->stockOf($p));
        $this->assertSame(700.0, (float) $r->fresh()->current_due);
        $this->assertSame(700.0, (float) $r->ledgerEntries()->latest('id')->value('balance_after'));
    }

    public function test_damaged_and_company_return_lines_do_not_restock(): void
    {
        $p = $this->product();
        $r = $this->retailer(['current_due' => 500]);
        $request = $this->returnRequest($r, $p, 2, 50);
        $request->update(['status' => 'approved']);

        $c = Livewire::test(ReturnReceiveCreate::class, ['returnRequest' => $request]);
        $lines = $c->get('lines');
        $lines[0]['disposition'] = 'company_return';
        $c->set('lines', $lines)->call('save')->assertHasNoErrors();

        $this->assertSame(0.0, $this->stockOf($p));
        $this->assertSame(400.0, (float) $r->fresh()->current_due);
        $this->assertSame(1, CompanyReturn::where('company_id', $p->company_id)->where('status', 'draft')->count());
    }

    public function test_rejected_return_cannot_be_approved_later(): void
    {
        $request = $this->returnRequest($this->retailer(), $this->product(), 1, 10);

        Livewire::test(ReturnRequestShow::class, ['returnRequest' => $request])->call('reject');
        Livewire::test(ReturnRequestShow::class, ['returnRequest' => $request->fresh()])->call('approve');

        $this->assertSame('rejected', $request->fresh()->status);
    }

    // ---- Warranty ------------------------------------------------------

    public function test_warranty_status_moves_only_forward_and_is_tracked(): void
    {
        $claim = WarrantyClaim::create([
            'number' => 'WAR-1', 'retailer_id' => $this->retailer()->id, 'product_id' => $this->product()->id,
            'claim_date' => today(), 'status' => 'pending',
        ]);

        // Cannot jump straight to repaired.
        Livewire::test(WarrantyClaimShow::class, ['warrantyClaim' => $claim])->call('markRepaired');
        $this->assertSame('pending', $claim->fresh()->status);

        Livewire::test(WarrantyClaimShow::class, ['warrantyClaim' => $claim->fresh()])->set('note', 'Checked, valid')->call('approve');
        Livewire::test(WarrantyClaimShow::class, ['warrantyClaim' => $claim->fresh()])->call('markReplaced');
        Livewire::test(WarrantyClaimShow::class, ['warrantyClaim' => $claim->fresh()])->call('reject');   // too late

        $this->assertSame('replaced', $claim->fresh()->status);
        $this->assertSame(['approved', 'replaced'], $claim->tracking()->orderBy('id')->pluck('status')->all());
    }

    // ---- Payroll -------------------------------------------------------

    public function test_payroll_net_salary_formula_and_one_entry_per_period(): void
    {
        $rep = Representative::create([
            'employee_code' => 'E1', 'name' => 'Rep One', 'salary_base' => 15000,
            'commission_rate' => 2, 'target_amount' => 100000, 'active' => true,
        ]);
        $r = $this->retailer(['representative_id' => $rep->id]);
        $period = today()->format('Y-m');

        $order = SalesOrder::create([
            'number' => 'SO-P', 'order_type' => 'retailer', 'retailer_id' => $r->id, 'representative_id' => $rep->id,
            'warehouse_id' => $this->warehouse()->id, 'status' => 'completed', 'order_date' => today(),
            'subtotal' => 50000, 'discount' => 0, 'grand_total' => 50000,
        ]);
        SalesInvoice::create([
            'number' => 'INV-P', 'sales_order_id' => $order->id, 'retailer_id' => $r->id, 'invoice_date' => today(),
            'subtotal' => 50000, 'discount' => 0, 'grand_total' => 50000, 'paid_amount' => 0, 'due_amount' => 50000, 'status' => 'unpaid',
        ]);
        // A received return of 5,000 lowers the commission base.
        $ret = $this->returnRequest($r, $this->product(), 50, 100);
        $ret->update(['status' => 'received']);

        Bonus::create(['representative_id' => $rep->id, 'amount' => 1000, 'bonus_date' => today()]);
        Advance::create(['representative_id' => $rep->id, 'amount' => 3000, 'advance_date' => today(), 'deducted' => false]);

        Livewire::test(PayrollCreate::class)->set('period', $period)->call('generate')->assertHasNoErrors();

        $entry = PayrollEntry::sole();
        // commission = 2% of (50,000 − 5,000) = 900; net = 15,000 + 900 + 1,000 − 3,000
        $this->assertSame(900.0, (float) $entry->commission_amount);
        $this->assertSame(13900.0, (float) $entry->net_salary);
        $this->assertTrue((bool) Advance::sole()->deducted);

        Livewire::test(PayrollCreate::class)->set('period', $period)->call('generate')->assertHasErrors('period');
        $this->assertSame(1, PayrollEntry::count());
    }

    // ---- Reports -------------------------------------------------------

    public function test_every_report_page_and_csv_opens(): void
    {
        $p = $this->product();
        $this->receive($p, 5);

        foreach (['sales', 'stock', 'collections', 'returns', 'representatives', 'purchase', 'companies', 'products', 'retailers', 'warranty', 'payroll', 'profit', 'expiry', 'areas'] as $report) {
            $this->get(route('reports.'.$report))->assertOk();
            $this->get(route('reports.export.'.$report))->assertOk();
        }

        $this->assertGreaterThan(0, StockLedger::count());
    }
}
