<?php

namespace Tests\Feature;

use App\Livewire\Reports\Vat as VatReport;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Settings\AppSetting;
use App\Models\User;
use App\Models\Vat\VatReturn;
use App\Services\Accounts\LedgerService;
use App\Services\Vat\VatReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Monthly VAT return: output VAT less input VAT (local 6.3 + import), settlement voucher. */
class VatReturnTest extends TestCase
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
        AppSetting::put(['vat_enabled' => '1', 'vat_bin' => '000000000-0000']);
        $this->travelTo('2026-11-10 10:00:00');
    }

    private function bal(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    /** A head-office sale in October with this much VAT inside it. */
    private function sale(float $vat, string $date = '2026-10-15'): void
    {
        app(LedgerService::class)->post(['type' => 'auto', 'entry_date' => $date], [
            ['account' => 'cash', 'debit' => 1000 + $vat],
            ['account' => 'sales', 'credit' => 1000],
            ['account' => 'vat_output', 'credit' => $vat],
        ]);
    }

    /** A local purchase received in October with the supplier's VAT on it. */
    private function purchaseWithVat(float $vat): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $p = $this->product(['type' => 'trading']);
        $company = \App\Models\Company::first();
        Livewire::test(\App\Livewire\Purchase\Orders\Create::class)
            ->set('company_id', $company->id)->set('warehouse_id', $this->warehouse()->id)
            ->set('items', [['product_id' => $p->id, 'quantity' => 10, 'unit_price' => 50]])
            ->call('save');
        $po = PurchaseOrder::latest('id')->first();
        $po->update(['status' => 'sent']);
        $c = Livewire::test(\App\Livewire\Purchase\Receipts\Create::class, ['purchaseOrder' => $po]);
        $lines = $c->get('lines');
        $lines[0]['batch_no'] = 'V1';
        $c->set('lines', $lines)->set('supplier_invoice_no', 'SUP-6.3-77')->set('vat_amount', (string) $vat)
            ->call('save')->assertHasNoErrors();
        $this->travelTo('2026-11-10 10:00:00');
    }

    public function test_input_vat_on_a_goods_receipt_is_owed_to_the_supplier_and_claimable(): void
    {
        $this->purchaseWithVat(75);

        $this->assertSame(75.0, $this->bal('vat_input'));
        $this->assertSame(500.0, $this->bal('inventory'));
        $this->assertSame(-575.0, $this->bal('ap'));

        $rows = app(VatReturnService::class)->purchaseRegister('2026-10');
        $this->assertSame('SUP-6.3-77', $rows[0]['invoice']);
        $this->assertSame(75.0, $rows[0]['vat']);
    }

    public function test_the_month_is_settled_net_of_input_vat(): void
    {
        $this->sale(150);
        $this->purchaseWithVat(60);

        $s = app(VatReturnService::class)->summary('2026-10');
        $this->assertSame(150.0, $s['output_month']);
        $this->assertSame(60.0, $s['input_month']);
        $this->assertSame(90.0, $s['payable']);

        Livewire::test(VatReport::class, ['month' => '2026-10'])
            ->assertSee('90.00')
            ->set('paid_on', '2026-11-10')->set('reference', 'TR-1')
            ->call('settle')->assertHasNoErrors();

        $r = VatReturn::first();
        $this->assertSame(90.0, (float) $r->paid);
        $this->assertSame(0.0, $this->bal('vat_output'));
        $this->assertSame(0.0, $this->bal('vat_input'));
        $this->assertSame(-90.0, $this->bal('bank'));

        // Once only, and undo reverses the voucher.
        $this->expectExceptionMessage('already settled');
        try {
            app(VatReturnService::class)->settle('2026-10', 'bank', '2026-11-10');
        } finally {
            Livewire::test(VatReport::class, ['month' => '2026-10'])->call('undo');
            $this->assertSame(0, VatReturn::count());
            $this->assertSame(150.0, -$this->bal('vat_output'));
            $this->assertSame(60.0, $this->bal('vat_input'));
        }
    }

    public function test_extra_input_vat_carries_forward(): void
    {
        $this->sale(40);
        $this->purchaseWithVat(100);

        $r = app(VatReturnService::class)->settle('2026-10', 'bank', '2026-11-05');
        $this->assertSame(40.0, (float) $r->set_off);
        $this->assertSame(0.0, (float) $r->paid);
        $this->assertSame(60.0, (float) $r->carried_forward);
        $this->assertSame(60.0, $this->bal('vat_input'));

        // November: 100 output, 60 brought forward → 40 to pay.
        $this->sale(100, '2026-11-08');
        $this->travelTo('2026-12-02 10:00:00');
        $s = app(VatReturnService::class)->summary('2026-11');
        $this->assertSame(60.0, $s['brought_forward']);
        $this->assertSame(40.0, $s['payable']);
    }

    public function test_settling_needs_the_month_to_be_over_and_accounts_permission(): void
    {
        $this->sale(10, '2026-11-02');
        Livewire::test(VatReport::class, ['month' => '2026-11'])->assertDontSee('Settle this month');

        $this->actingAs($this->userWithRole('Manager'));
        Livewire::test(VatReport::class, ['month' => '2026-10'])->call('settle')->assertForbidden();
    }

    public function test_a_received_return_prints_a_mushak_6_7_credit_note(): void
    {
        $p = $this->product(['vat_rate' => 15]);
        $r = $this->retailer();
        $req = \App\Models\Returns\ReturnRequest::create(['number' => 'RR-1', 'retailer_id' => $r->id, 'request_date' => today(), 'status' => 'received']);
        $req->items()->create(['product_id' => $p->id, 'quantity' => 2, 'unit_price' => 115]);
        $rec = \App\Models\Returns\ReturnReceive::create(['number' => 'RCV-1', 'return_request_id' => $req->id, 'warehouse_id' => $this->warehouse()->id, 'receive_date' => today()]);
        $rec->items()->create(['product_id' => $p->id, 'quantity' => 2, 'disposition' => 'restock']);

        $res = $this->get(route('returns.credit-note', $rec))->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());

        AppSetting::put(['vat_enabled' => '0']);
        $this->get(route('returns.credit-note', $rec))->assertNotFound();
    }
}