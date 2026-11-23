<?php

namespace Tests\Feature;

use App\Livewire\Accounts\SupplierPayments;
use App\Livewire\Collection\Create as CollectionForm;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\Company;
use App\Models\Settings\AppSetting;
use App\Models\Vat\VdsCertificate;
use App\Services\Vat\VatReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** VAT deducted at source (Mushak 6.6): from supplier payments and by customers. */
class VdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->actingAs($this->userWithRole('Admin'));
        AppSetting::put(['vat_enabled' => '1', 'vat_bin' => '123456789-0101']);
        $this->travelTo('2026-11-10 10:00:00');
    }

    private function bal(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    public function test_paying_a_supplier_with_vds_issues_a_6_6_and_it_is_paid_with_the_return(): void
    {
        $supplier = Company::create(['name' => 'Packaging Ltd', 'code' => 'PKG', 'bin' => '987654321-0202']);
        $this->travelTo('2026-10-20 10:00:00');
        Livewire::test(SupplierPayments::class)
            ->set('company_id', $supplier->id)->set('amount', '10000')->set('vds_amount', '1500')
            ->set('invoice_no', 'PKG-6.3-55')->set('invoice_amount', '11500')
            ->call('save')->assertHasNoErrors()->assertSee('VDS certificate');

        $c = VdsCertificate::first();
        $this->assertSame('issued', $c->direction);
        $this->assertSame(-1500.0, $this->bal('vds_payable'));
        $this->assertSame(11500.0, $this->bal('ap'));   // supplier's due cleared by 10,000 + 1,500
        $res = $this->get(route('accounts.vds.pdf', $c))->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());

        // October return: nothing on sales, but the VDS is deposited.
        $this->travelTo('2026-11-10 10:00:00');
        $s = app(VatReturnService::class)->summary('2026-10');
        $this->assertSame(1500.0, $s['vds_payable']);
        $this->assertSame(1500.0, $s['total_payable']);
        $r = app(VatReturnService::class)->settle('2026-10', 'bank', '2026-11-10');
        $this->assertSame(1500.0, (float) $r->vds_paid);
        $this->assertSame(0.0, $this->bal('vds_payable'));
    }

    public function test_a_supplier_vds_needs_the_invoice_number(): void
    {
        $supplier = Company::create(['name' => 'X', 'code' => 'X1']);
        Livewire::test(SupplierPayments::class)
            ->set('company_id', $supplier->id)->set('amount', '100')->set('vds_amount', '15')
            ->call('save')->assertHasErrors('invoice_no');
        $this->assertSame(0, VdsCertificate::count());
    }

    public function test_a_customers_vds_clears_their_due_and_lowers_output_vat(): void
    {
        $r = $this->retailer(['current_due' => 0]);
        app(\App\Services\Accounts\OpeningBalanceService::class)->save('2026-11-01', [], [$r->id => 1150], []);
        app(\App\Services\Accounts\LedgerService::class)->post(['type' => 'auto', 'entry_date' => '2026-11-02'], [
            ['account' => 'cash', 'debit' => 1150], ['account' => 'sales', 'credit' => 1000], ['account' => 'vat_output', 'credit' => 150],
        ]);

        Livewire::test(CollectionForm::class)
            ->set('retailer_id', $r->id)->set('amount', '150')->set('method', 'vds')->set('reference_no', 'GOV-6.6-9')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1000.0, (float) $r->fresh()->current_due);
        $this->assertSame(0.0, $this->bal('vat_output'));
        $this->assertSame('GOV-6.6-9', VdsCertificate::where('direction', 'received')->value('certificate_no'));
        $this->assertSame(150.0, app(VatReturnService::class)->summary('2026-11')['vds_received_month']);

        // Without the certificate number it is refused.
        Livewire::test(CollectionForm::class)
            ->set('retailer_id', $r->id)->set('amount', '10')->set('method', 'vds')->call('save')->assertHasErrors('reference_no');
    }
}
