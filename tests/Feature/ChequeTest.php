<?php

namespace Tests\Feature;

use App\Livewire\Collection\Cheques;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\Cheque;
use App\Models\PaymentCollection;
use App\Models\User;
use App\Services\Accounts\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Customers' cheques: in hand → deposited → cleared (a collection) or bounced (collection voided). */
class ChequeTest extends TestCase
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

    private function bal(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    /** A customer who owes 1000 (opening balance). */
    private function customerOwing(): \App\Models\Retailer
    {
        $r = $this->retailer();
        app(\App\Services\Accounts\OpeningBalanceService::class)->save(today()->subMonth()->toDateString(), [], [$r->id => 1000], []);

        return $r->fresh();
    }

    private function receiveCheque(\App\Models\Retailer $r, float $amount = 600): Cheque
    {
        Livewire::test(Cheques::class)->call('create')
            ->set('retailer_id', $r->id)->set('cheque_no', '000123')->set('bank_name', 'Sonali Bank')
            ->set('cheque_date', today()->addDays(10)->toDateString())->set('amount', (string) $amount)
            ->call('save')->assertHasNoErrors();

        return Cheque::latest('id')->first();
    }

    private function act(Cheque $c, string $action, array $extra = [])
    {
        $t = Livewire::test(Cheques::class)->call('openAction', $c->id, $action);
        foreach ($extra as $k => $v) {
            $t->set($k, $v);
        }

        return $t->call('confirmAction');
    }

    public function test_a_post_dated_cheque_changes_nothing_until_it_clears(): void
    {
        $r = $this->customerOwing();
        $c = $this->receiveCheque($r);

        $this->assertSame('in_hand', $c->status);
        $this->assertSame(1000.0, (float) $r->fresh()->current_due);
        $this->assertSame(0, PaymentCollection::count());

        $this->act($c, 'deposit')->assertHasNoErrors();
        $this->assertSame('deposited', $c->fresh()->status);

        $this->act($c, 'clear')->assertHasNoErrors();
        $c->refresh();
        $this->assertSame('cleared', $c->status);
        $this->assertNotNull($c->collection_id);
        $this->assertSame('bank', $c->collection->method);
        $this->assertSame(400.0, (float) $r->fresh()->current_due);
        $this->assertSame(600.0, $this->bal('bank'));
    }

    public function test_a_bounce_after_clearing_voids_the_collection_and_bills_the_charge(): void
    {
        $r = $this->customerOwing();
        $c = $this->receiveCheque($r);
        $this->act($c, 'clear');

        $this->act($c->fresh(), 'bounce', ['bounce_charge' => '150', 'reason' => 'Insufficient funds'])->assertHasNoErrors();

        $c->refresh();
        $this->assertSame('bounced', $c->status);
        $this->assertNull($c->collection_id);
        $this->assertSame(0, PaymentCollection::count());
        $this->assertSame(1150.0, (float) $r->fresh()->current_due);          // owes it again, plus the charge
        $this->assertSame(1150.0, $this->bal('ar'));
        $this->assertSame(-150.0, $this->bal('bank'));                         // the bank took its charge
    }

    public function test_steps_out_of_order_are_refused(): void
    {
        $r = $this->customerOwing();
        $c = $this->receiveCheque($r);

        $this->act($c, 'bounce')->assertHasErrors('action_date');             // never deposited
        $this->act($c, 'return', ['reason' => 'Paid in cash'])->assertHasNoErrors();
        $this->assertSame('returned', $c->fresh()->status);
        $this->act($c->fresh(), 'clear')->assertHasErrors('action_date');
        $this->assertSame(1000.0, (float) $r->fresh()->current_due);
    }

    public function test_only_finance_can_bounce_a_cleared_cheque(): void
    {
        $r = $this->customerOwing();
        $c = $this->receiveCheque($r);
        $this->act($c, 'clear');

        $rep = $this->userWithRole('Sales Representative');
        $rep->givePermissionTo('collection.manage');
        $this->actingAs($rep);
        Livewire::test(Cheques::class)->call('openAction', $c->id, 'bounce')->call('confirmAction')->assertForbidden();
    }

    public function test_the_page_lists_and_filters(): void
    {
        $r = $this->customerOwing();
        $this->receiveCheque($r);
        $this->get(route('collection.cheques'))->assertOk()->assertSee('000123')->assertSee('Sonali Bank');
        Livewire::test(Cheques::class)->set('filter', 'cleared')->assertDontSee('000123');
    }
}
