<?php

namespace Tests\Feature;

use App\Console\Commands\AccountsBackfill;
use App\Livewire\Collection\Create as CollectionCreate;
use App\Livewire\Delivery\Create as DeliveryCreate;
use App\Livewire\Sales\Orders\Create as OrderCreate;
use App\Livewire\Sales\Orders\Show as OrderShow;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Models\Area;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Services\Accounts\AccountReports;
use App\Services\Accounts\AutoPosting;
use App\Services\Accounts\LedgerService;
use App\Services\Accounts\YearClosing;
use App\Services\DealerService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2 of the ERP plan: double-entry accounts behind everyday work.
 */
class AccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole('Admin');
        $this->actingAs($this->admin);
    }

    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    private function reports(): AccountReports
    {
        return app(AccountReports::class);
    }

    /** Stock worth 700 on the books (opening), then sell 3 × 100 and collect 200. */
    private function sellAndCollect($retailer = null): array
    {
        $p = $this->product(['sale_price' => 100, 'purchase_price' => 70]);
        $this->receive($p, 10, 'B1', 70);
        $this->ledger()->post(['type' => 'opening', 'entry_date' => today()->subDays(5)], [
            ['account' => 'inventory', 'debit' => 700],
            ['account' => 'opening_equity', 'credit' => 700],
        ]);

        $retailer ??= $this->retailer();
        Livewire::test(OrderCreate::class)
            ->set('retailer_id', $retailer->id)->set('warehouse_id', $this->warehouse()->id)
            ->call('addProduct', $p->id)->set('items.0.quantity', 3)->call('save')->assertHasNoErrors();
        $order = SalesOrder::latest('id')->first();
        Livewire::test(OrderShow::class, ['salesOrder' => $order])->call('approve');
        Livewire::test(DeliveryCreate::class, ['salesOrder' => $order->fresh()])->call('save')->assertHasNoErrors();

        Livewire::test(CollectionCreate::class)
            ->set('retailer_id', $retailer->id)->set('amount', '200')->set('method', 'cash')
            ->call('save')->assertHasNoErrors();

        return [$p, $retailer];
    }

    private function balance(string $key, $totals): float
    {
        $a = Account::bySystemKey($key);
        $t = $totals[$a->id] ?? null;

        return round(($a->isDebitNature() ? 1 : -1) * ((float) ($t->debit ?? 0) - (float) ($t->credit ?? 0)), 2);
    }

    public function test_the_default_chart_is_in_place(): void
    {
        foreach (['cash', 'bank', 'ar', 'ap', 'inventory', 'sales', 'cogs', 'salary', 'retained_earnings', 'opening_equity'] as $key) {
            $this->assertNotNull(Account::bySystemKey($key));
        }
        $this->assertTrue(Account::where('code', '1000')->first()->is_group);
    }

    public function test_a_sale_and_its_collection_post_balanced_entries(): void
    {
        [, $retailer] = $this->sellAndCollect();

        $totals = $this->reports()->totals();
        $this->assertSame(200.0, $this->balance('cash', $totals));
        $this->assertSame(100.0, $this->balance('ar', $totals), 'matches what the customer still owes');
        $this->assertSame((float) $retailer->fresh()->current_due, $this->balance('ar', $totals));
        $this->assertSame(300.0, $this->balance('sales', $totals));
        $this->assertSame(210.0, $this->balance('cogs', $totals), 'cost of the 3 units that left (3 × 70)');
        $this->assertSame(490.0, $this->balance('inventory', $totals));

        $tb = $this->reports()->trialBalance(today()->subYear()->toDateString(), today()->toDateString());
        $this->assertEquals($tb['debit'], $tb['credit'], 'the trial balance balances');

        $pl = $this->reports()->profitLoss(today()->startOfYear()->toDateString(), today()->toDateString());
        $this->assertEquals(300, $pl['revenue']);
        $this->assertEquals(90, $pl['gross']);
        $this->assertEquals(90, $pl['net']);

        $bs = $this->reports()->balanceSheet(today()->toDateString());
        $this->assertEquals(790, $bs['total_assets']);            // 200 cash + 100 due + 490 stock
        $this->assertEquals(790, $bs['total_liabilities_equity']); // 700 opening + 90 profit
        $this->assertEquals(90, $bs['earnings']);

        // The collection is not posted twice when the backfill runs.
        $before = JournalEntry::count();
        AccountsBackfill::postAll(app(AutoPosting::class));
        AccountsBackfill::postAll(app(AutoPosting::class));
        $this->assertSame($before, JournalEntry::count());
    }

    public function test_bad_vouchers_are_refused(): void
    {
        $cases = [
            'unbalanced' => [['account' => 'cash', 'debit' => 100], ['account' => 'capital', 'credit' => 90]],
            'group account' => [['account' => (int) Account::where('code', '1100')->value('id'), 'debit' => 100], ['account' => 'capital', 'credit' => 100]],
            'receivable without a customer' => [['account' => 'ar', 'debit' => 100], ['account' => 'sales', 'credit' => 100]],
            'one line only' => [['account' => 'cash', 'debit' => 100]],
        ];

        foreach ($cases as $name => $lines) {
            try {
                $this->ledger()->post(['type' => 'journal', 'entry_date' => today(), 'manual' => true], $lines);
                $this->fail("accepted: $name");
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_reversing_a_voucher_cancels_it_out(): void
    {
        $entry = $this->ledger()->post(['type' => 'payment', 'entry_date' => today(), 'manual' => true], [
            ['account' => (int) Account::where('code', '5240')->value('id'), 'debit' => 5000, 'narration' => 'Rent'],
            ['account' => 'cash', 'credit' => 5000],
        ]);
        $this->ledger()->reverse($entry, null, 'wrong month');

        $this->assertSame(0.0, $this->balance('cash', $this->reports()->totals()));
        $this->expectException(\RuntimeException::class);
        $this->ledger()->reverse($entry->fresh());   // only once
    }

    public function test_closing_a_year_moves_profit_and_locks_it(): void
    {
        $lastYear = \App\Support\FiscalYear::for(today()->subYear());
        $date = $lastYear['start']->addMonth()->toDateString();

        $this->ledger()->post(['type' => 'receipt', 'entry_date' => $date, 'manual' => true], [
            ['account' => 'cash', 'debit' => 1000],
            ['account' => 'other_income', 'credit' => 1000],
        ]);
        $this->ledger()->post(['type' => 'payment', 'entry_date' => $date, 'manual' => true], [
            ['account' => (int) Account::where('code', '5240')->value('id'), 'debit' => 400],
            ['account' => 'cash', 'credit' => 400],
        ]);

        app(YearClosing::class)->close($date);

        $after = $this->reports()->totals(null, $lastYear['end']->toDateString());
        $this->assertSame(600.0, $this->balance('retained_earnings', $after));
        $this->assertSame(0.0, $this->balance('other_income', $after));
        $this->assertEquals(0, $this->reports()->balanceSheet($lastYear['end']->toDateString())['earnings']);

        $this->expectException(\RuntimeException::class);
        $this->ledger()->post(['type' => 'journal', 'entry_date' => $date, 'manual' => true], [
            ['account' => 'cash', 'debit' => 1], ['account' => 'capital', 'credit' => 1],
        ]);
    }

    public function test_a_dealer_keeps_its_own_books(): void
    {
        $area = Area::firstOrCreate(['code' => 'AR1'], ['name' => 'Area 1']);
        $dealer = app(DealerService::class)->create(
            ['code' => 'A', 'name' => 'Dealer A', 'phone' => '0170', 'area_id' => $area->id],
            100000,
            ['name' => 'A', 'email' => 'a@dealer.test', 'password' => 'secret-123'],
        );

        // Head office sells 3 × 100 to the dealer and the dealer pays 200.
        $this->sellAndCollect($dealer->account);

        $hq = $this->reports()->totals();
        $this->assertSame(100.0, $this->balance('ar', $hq));
        $this->assertSame(300.0, $this->balance('sales', $hq));

        Tenant::flush();
        $this->actingAs(User::where('email', 'a@dealer.test')->first());
        $own = $this->reports()->totals();
        $this->assertSame(300.0, $this->balance('inventory', $own), 'the goods, at what the dealer paid');
        $this->assertSame(100.0, $this->balance('ap', $own), 'still owed to Agro Tech');
        $this->assertSame(-200.0, $this->balance('cash', $own), 'the dealer\'s cash went out (no opening cash entered yet)');
        $this->assertSame(0.0, $this->balance('sales', $own), 'the head office sale is not in the dealer\'s books');

        $tb = $this->reports()->trialBalance(today()->subYear()->toDateString(), today()->toDateString());
        $this->assertEquals($tb['debit'], $tb['credit']);

        $aging = $this->reports()->aging('ap', today()->toDateString());
        $this->assertSame('head_office', $aging['rows'][0]['party_type']);
        $this->assertEquals(100, $aging['total']);
    }

    public function test_aging_sets_payments_against_the_oldest_amounts(): void
    {
        $r = $this->retailer();
        $sale = fn (string $date, float $amount) => $this->ledger()->post(['type' => 'journal', 'entry_date' => $date], [
            ['account' => 'ar', 'debit' => $amount, 'party_type' => 'retailer', 'party_id' => $r->id],
            ['account' => 'sales', 'credit' => $amount],
        ]);

        $sale(today()->subDays(100)->toDateString(), 500);   // 90+
        $sale(today()->subDays(45)->toDateString(), 300);    // 31-60
        $sale(today()->subDays(5)->toDateString(), 200);     // 0-30
        $this->ledger()->post(['type' => 'receipt', 'entry_date' => today()], [
            ['account' => 'cash', 'debit' => 600],
            ['account' => 'ar', 'credit' => 600, 'party_type' => 'retailer', 'party_id' => $r->id],
        ]);

        $aging = $this->reports()->aging('ar', today()->toDateString());
        $row = $aging['rows'][0];
        $this->assertSame(0.0, $row['buckets']['90+'], 'the oldest 500 is paid first');
        $this->assertSame(200.0, $row['buckets']['31-60'], '100 of the 300 is paid');
        $this->assertSame(200.0, $row['buckets']['0-30']);
        $this->assertSame(400.0, $row['balance']);
    }
}
