<?php

namespace Tests\Feature;

use App\Livewire\Accounts\BankReconciliation;
use App\Livewire\Accounts\ChartOfAccounts;
use App\Livewire\Accounts\Expenses;
use App\Livewire\Accounts\OpeningBalances;
use App\Livewire\Accounts\SupplierPayments;
use App\Livewire\Accounts\Vouchers\Create as VoucherCreate;
use App\Livewire\Accounts\Vouchers\Show as VoucherShow;
use App\Livewire\Accounts\YearClosings;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Models\Accounts\JournalLine;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounts\AccountReports;
use App\Services\Accounts\ReportData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The accounting screens, as an Admin and as an Accountant who may not approve. */
class AccountsScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->userWithRole('Admin'));
    }

    private function acc(string $code): int
    {
        return (int) Account::where('code', $code)->value('id');
    }

    public function test_voucher_entry_draft_then_approve(): void
    {
        $c = Livewire::test(VoucherCreate::class, ['type' => 'payment'])
            ->set('narration', 'Office rent November')
            ->set('lines.0.account_id', $this->acc('5240'))->set('lines.0.debit', '12000')
            ->set('lines.1.account_id', Account::bySystemKey('cash')->id)->set('lines.1.credit', '12000')
            ->call('save', false)
            ->assertHasNoErrors();

        $entry = JournalEntry::firstOrFail();
        $this->assertSame('draft', $entry->status);
        $this->assertSame('payment', $entry->type);
        $this->assertSame(0.0, (float) app(AccountReports::class)->totals()->sum('debit'), 'a draft does not count');

        Livewire::test(VoucherShow::class, ['entry' => $entry])->call('approve')->assertHasNoErrors();
        $this->assertSame('posted', $entry->fresh()->status);
        $this->get(route('accounts.vouchers.pdf', $entry))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Unbalanced and missing-party vouchers are refused with a message.
        Livewire::test(VoucherCreate::class, ['type' => 'journal'])
            ->set('lines.0.account_id', $this->acc('5240'))->set('lines.0.debit', '100')
            ->set('lines.1.account_id', Account::bySystemKey('cash')->id)->set('lines.1.credit', '90')
            ->call('save', true)
            ->assertHasErrors('lines');
        Livewire::test(VoucherCreate::class, ['type' => 'receipt'])
            ->set('lines.0.account_id', Account::bySystemKey('cash')->id)->set('lines.0.debit', '100')
            ->set('lines.1.account_id', Account::bySystemKey('ar')->id)->set('lines.1.credit', '100')
            ->call('save', true)
            ->assertHasErrors('lines.1.party_id');
    }

    public function test_an_accountant_without_approval_only_makes_drafts(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $accountant = $this->userWithRole('Accountant');
        $accountant->roles()->first()->revokePermissionTo('accounts.approve');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($accountant->fresh());

        Livewire::test(Expenses::class)
            ->set('expense_account_id', $this->acc('5250'))->set('amount', '3500')->set('note', 'Electricity')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('draft', JournalEntry::firstOrFail()->status);
    }

    public function test_expense_and_supplier_payment(): void
    {
        Livewire::test(Expenses::class)
            ->set('expense_account_id', $this->acc('5260'))->set('amount', '800')
            ->call('save')->assertHasNoErrors();

        $company = Company::create(['name' => 'Seed Co', 'code' => 'SEED']);
        app(\App\Services\Accounts\LedgerService::class)->post(['type' => 'auto', 'entry_date' => today()], [
            ['account' => 'inventory', 'debit' => 5000],
            ['account' => 'ap', 'credit' => 5000, 'party_type' => 'company', 'party_id' => $company->id],
        ]);

        Livewire::test(SupplierPayments::class)
            ->assertSee('Seed Co')
            ->call('pay', $company->id)
            ->set('amount', '3000')
            ->call('save')->assertHasNoErrors();

        $aging = app(AccountReports::class)->aging('ap', today()->toDateString());
        $this->assertEquals(2000, collect($aging['rows'])->firstWhere('party_id', $company->id)['balance']);
    }

    public function test_opening_balances_also_set_customer_due(): void
    {
        $r = $this->retailer(['current_due' => 0]);

        Livewire::test(OpeningBalances::class)
            ->set('entry_date', today()->startOfMonth()->toDateString())
            ->set('amounts.'.Account::bySystemKey('cash')->id, '50000')
            ->set('amounts.'.Account::bySystemKey('capital')->id, '40000')
            ->set('customers.'.$r->id, '2500')
            ->call('save')->assertHasNoErrors();

        $this->assertEquals(2500, (float) $r->fresh()->current_due);
        $entry = JournalEntry::where('type', 'opening')->firstOrFail();
        $this->assertEquals(52500, (float) $entry->lines()->sum('debit'));
        $this->assertEquals(12500, (float) $entry->lines()->whereHas('account', fn ($q) => $q->where('system_key', 'opening_equity'))->sum('credit'));

        // Saving again with a different due replaces the voucher and adjusts the due by the difference.
        Livewire::test(OpeningBalances::class)->assertSet('customers.'.$r->id, '2500')
            ->set('customers.'.$r->id, '1000')->call('save')->assertHasNoErrors();
        $this->assertEquals(1000, (float) $r->fresh()->current_due);
        $this->assertSame(1, JournalEntry::where('type', 'opening')->count());
    }

    public function test_bank_reconciliation_and_year_closing_screens(): void
    {
        $bank = Account::bySystemKey('bank');
        app(\App\Services\Accounts\LedgerService::class)->post(['type' => 'receipt', 'entry_date' => today()], [
            ['account' => 'bank', 'debit' => 1000],
            ['account' => 'capital', 'credit' => 1000],
        ]);
        $line = JournalLine::where('account_id', $bank->id)->first();

        Livewire::test(BankReconciliation::class)
            ->set('account_id', $bank->id)
            ->set('statement_balance', '1000')
            ->assertViewHas('difference', 1000.0)
            ->call('toggle', $line->id)
            ->assertViewHas('difference', 0.0);
        $this->assertNotNull($line->fresh()->cleared_at);

        Livewire::test(YearClosings::class)->assertOk()->assertSee(\App\Support\FiscalYear::current()['label']);
    }

    public function test_chart_and_reports_open(): void
    {
        Livewire::test(ChartOfAccounts::class)
            ->call('create', $this->acc('1120'))
            ->assertSet('type', 'asset')
            ->set('name', 'Dutch-Bangla Bank')->set('is_bank', true)->set('bank_account_no', '123-456')
            ->call('save')->assertHasNoErrors();
        $this->assertTrue(Account::where('name', 'Dutch-Bangla Bank')->first()->is_bank);

        Livewire::test(ChartOfAccounts::class)->call('delete', Account::bySystemKey('cash')->id)->assertHasErrors('delete');

        foreach (array_keys(ReportData::REPORTS) as $report) {
            $this->get(route('accounts.reports', $report))->assertOk();
            $this->get(route('accounts.reports.pdf', $report))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        $this->get(route('accounts.ledger', ['account' => Account::bySystemKey('cash')->id]))->assertOk();

        foreach (['accounts.chart', 'accounts.vouchers.index', 'accounts.vouchers.create', 'accounts.expenses', 'accounts.supplier-payments', 'accounts.opening', 'accounts.bank', 'accounts.year-closing'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
