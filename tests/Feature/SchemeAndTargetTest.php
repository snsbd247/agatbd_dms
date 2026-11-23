<?php

namespace Tests\Feature;

use App\Livewire\Dealers\Targets;
use App\Livewire\Sales\Orders\Create as OrderForm;
use App\Livewire\Sales\Schemes;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\Dealer;
use App\Models\DealerTarget;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Models\Scheme;
use App\Models\User;
use App\Services\DealerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Trade schemes on sales orders, and dealer targets with incentives. */
class SchemeAndTargetTest extends TestCase
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

    private function scheme(array $attrs): Scheme
    {
        return Scheme::create($attrs + ['name' => 'Offer', 'audience' => 'all', 'starts_on' => today()->subDay(), 'ends_on' => today()->addMonth(), 'active' => true]);
    }

    private function order(Retailer $r, int $productId, float $qty): SalesOrder
    {
        Livewire::test(OrderForm::class)
            ->set('retailer_id', $r->id)->set('warehouse_id', $this->warehouse()->id)
            ->call('addProduct', $productId)->set('items.0.quantity', $qty)->call('save')->assertHasNoErrors();

        return SalesOrder::latest('id')->first();
    }

    public function test_a_discount_scheme_lowers_the_line_price(): void
    {
        $p = $this->product(['type' => 'finished_good', 'sale_price' => 200]);
        $r = $this->retailer(['credit_limit' => 1000000]);
        $this->scheme(['type' => 'discount', 'product_id' => $p->id, 'min_qty' => 10, 'discount_percent' => 5]);

        $small = $this->order($r, $p->id, 5);
        $this->assertSame(200.0, (float) $small->items()->value('unit_price'));

        $big = $this->order($r, $p->id, 10);
        $line = $big->items()->first();
        $this->assertSame(190.0, (float) $line->unit_price);
        $this->assertSame(200.0, (float) $line->list_price);
        $this->assertSame(1900.0, (float) $big->grand_total);
    }

    public function test_free_goods_are_added_and_recalculated_on_edit(): void
    {
        $p = $this->product(['type' => 'finished_good', 'sale_price' => 100]);
        $gift = $this->product(['type' => 'finished_good', 'sale_price' => 30]);
        $r = $this->retailer(['credit_limit' => 1000000]);
        $this->scheme(['type' => 'free_goods', 'product_id' => $p->id, 'min_qty' => 10, 'free_product_id' => $gift->id, 'free_qty' => 1]);

        $o = $this->order($r, $p->id, 25);
        $free = $o->items()->where('is_free', true)->first();
        $this->assertSame(2.0, (float) $free->quantity);
        $this->assertSame(0.0, (float) $free->unit_price);
        $this->assertSame(2500.0, (float) $o->grand_total);

        // Edit to 30: three free, never doubled.
        Livewire::test(OrderForm::class, ['salesOrder' => $o])
            ->assertCount('items', 1)
            ->set('items.0.quantity', 30)->call('save')->assertHasNoErrors();
        $this->assertSame(1, $o->items()->where('is_free', true)->count());
        $this->assertSame(3.0, (float) $o->items()->where('is_free', true)->value('quantity'));
    }

    public function test_audience_and_dates_limit_a_scheme(): void
    {
        $p = $this->product(['type' => 'finished_good', 'sale_price' => 100]);
        $r = $this->retailer(['credit_limit' => 1000000]);
        $this->scheme(['type' => 'discount', 'product_id' => $p->id, 'discount_percent' => 10, 'audience' => 'dealers']);
        $this->scheme(['type' => 'discount', 'product_id' => $p->id, 'discount_percent' => 20, 'starts_on' => today()->addDays(5)]);

        $o = $this->order($r, $p->id, 1);
        $this->assertSame(100.0, (float) $o->items()->value('unit_price'));
    }

    public function test_schemes_are_managed_on_their_page(): void
    {
        $p = $this->product(['type' => 'finished_good']);
        Livewire::test(Schemes::class)->call('create')
            ->set('name', 'Winter')->set('type', 'discount')->set('product_id', $p->id)->set('discount_percent', '7')
            ->call('save')->assertHasNoErrors();
        $s = Scheme::first();
        $this->assertSame('7.00', $s->discount_percent);

        Livewire::test(Schemes::class)->call('toggle', $s->id);
        $this->assertFalse($s->fresh()->active);
        $this->get(route('sales.schemes'))->assertOk()->assertSee('Winter');
    }

    public function test_a_dealer_reaching_the_target_gets_the_incentive_on_its_account(): void
    {
        $dealer = app(DealerService::class)->create(['name' => 'Gazipur Dealer', 'code' => 'GZP', 'phone' => '017', 'area_id' => \App\Models\Area::firstOrCreate(['code' => 'A1'], ['name' => 'Area'])->id], 500000);
        $account = Retailer::where('account_dealer_id', $dealer->id)->first();
        $month = today()->subMonth()->format('Y-m');
        SalesInvoice::create(['number' => 'INV-T1', 'retailer_id' => $account->id, 'invoice_date' => today()->subMonth()->startOfMonth()->addDays(3),
            'subtotal' => 120000, 'discount' => 0, 'grand_total' => 120000, 'paid_amount' => 0, 'due_amount' => 120000, 'status' => 'unpaid']);
        $account->update(['current_due' => 120000]);

        Livewire::test(Targets::class, ['month' => $month])
            ->set("inputs.{$dealer->id}.target", '100000')->set("inputs.{$dealer->id}.percent", '2')
            ->call('save')->assertSee('2,400.00');
        $t = DealerTarget::first();

        Livewire::test(Targets::class, ['month' => $month])->call('postIncentive', $t->id)->assertSee('Posted');

        $this->assertSame(2400.0, (float) $t->fresh()->incentive_amount);
        $this->assertSame(117600.0, (float) $account->fresh()->current_due);
        $id = Account::bySystemKey('dealer_incentive')->id;
        $this->assertSame(2400.0, (float) JournalLine::where('account_id', $id)->sum('debit'));

        // Once only.
        Livewire::test(Targets::class, ['month' => $month])->call('postIncentive', $t->id)->assertSee('already posted');
    }

    public function test_no_incentive_below_target_or_before_month_end(): void
    {
        $dealer = app(DealerService::class)->create(['name' => 'Bogura Dealer', 'code' => 'BOG', 'phone' => '018', 'area_id' => \App\Models\Area::firstOrCreate(['code' => 'A1'], ['name' => 'Area'])->id], 500000);
        $t = DealerTarget::create(['dealer_id' => $dealer->id, 'period' => today()->format('Y-m'), 'target_amount' => 1000, 'incentive_percent' => 5]);

        Livewire::test(Targets::class)->call('postIncentive', $t->id)->assertSee('not over yet');
        $t->update(['period' => today()->subMonth()->format('Y-m')]);
        Livewire::test(Targets::class)->call('postIncentive', $t->id)->assertSee('not reached');
        $this->assertNull($t->fresh()->posted_at);
    }
}
