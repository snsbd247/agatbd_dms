<?php

namespace Tests\Feature;

use App\Livewire\Accounts\FixedAssets;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\FixedAsset;
use App\Services\FixedAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Fixed asset register: purchase, monthly depreciation, disposal. */
class FixedAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->actingAs($this->userWithRole('Admin'));
        $this->travelTo('2026-11-10 10:00:00');
    }

    private function bal(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    private function machine(array $over = []): FixedAsset
    {
        return app(FixedAssetService::class)->add($over + [
            'name' => 'Mixer', 'category' => 'machinery', 'account_id' => Account::where('code', '1220')->value('id'),
            'purchase_date' => '2026-08-05', 'cost' => 120000, 'salvage_value' => 0, 'method' => 'straight_line',
            'useful_life_months' => 60, 'rate_percent' => 0, 'funded_by' => 'bank',
        ]);
    }

    public function test_buying_from_the_bank_posts_the_asset(): void
    {
        $a = $this->machine();
        $this->assertStringStartsWith('FA', $a->code);
        $this->assertSame(-120000.0, $this->bal('bank'));
        $machinery = Account::where('code', '1220')->value('id');
        $this->assertSame(120000.0, (float) JournalLine::where('account_id', $machinery)->sum('debit'));
    }

    public function test_straight_line_depreciation_catches_up_month_by_month_once(): void
    {
        $a = $this->machine();

        $posted = app(FixedAssetService::class)->run('2026-10');
        $this->assertSame(['2026-08' => 2000.0, '2026-09' => 2000.0, '2026-10' => 2000.0], $posted);
        $this->assertSame(6000.0, (float) $a->fresh()->accumulated_depreciation);
        $this->assertSame(6000.0, $this->bal('depreciation'));
        $this->assertSame(-6000.0, $this->bal('accum_depreciation'));

        $this->assertSame([], app(FixedAssetService::class)->run('2026-10'));
        $this->expectExceptionMessage('future month');
        app(FixedAssetService::class)->run('2027-01');
    }

    public function test_reducing_balance_and_salvage_floor(): void
    {
        $a = $this->machine(['method' => 'reducing_balance', 'rate_percent' => 12, 'cost' => 10000, 'salvage_value' => 9950]);
        $posted = app(FixedAssetService::class)->run('2026-10');
        // 1% a month of 10,000 = 100, but only 50 is left above salvage.
        $this->assertSame(['2026-08' => 50.0], $posted);
        $this->assertSame(9950.0, $a->fresh()->bookValue());
    }

    public function test_disposal_books_the_gain_or_loss(): void
    {
        $a = $this->machine();
        $this->expectsDisposeToNeedDepreciation($a);

        app(FixedAssetService::class)->run('2026-10');
        app(FixedAssetService::class)->dispose($a->fresh(), '2026-11-08', 110000, 'bank');

        $a->refresh();
        $this->assertSame('disposed', $a->status);
        // book value 114,000 sold for 110,000 → 4,000 loss
        $this->assertSame(4000.0, $this->bal('other_income'));
        $this->assertSame(0.0, $this->bal('accum_depreciation'));
        $machinery = Account::where('code', '1220')->value('id');
        $this->assertSame(0.0, round((float) JournalLine::where('account_id', $machinery)->sum('debit') - (float) JournalLine::where('account_id', $machinery)->sum('credit'), 2));
    }

    private function expectsDisposeToNeedDepreciation(FixedAsset $a): void
    {
        try {
            app(FixedAssetService::class)->dispose($a, '2026-11-08', 1, 'bank');
            $this->fail('Disposal before depreciation should be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('2026-10', $e->getMessage());
        }
    }

    public function test_the_page_adds_runs_and_locks_posted_fields(): void
    {
        Livewire::test(FixedAssets::class)->call('create')
            ->set('name', 'Delivery van')->set('category', 'vehicle')->set('cost', '2400000')->set('purchase_date', '2026-10-01')
            ->set('useful_life_months', '120')->set('funded_by', 'opening')
            ->call('save')->assertHasNoErrors();
        $van = FixedAsset::first();
        $this->assertSame(0.0, $this->bal('bank'));   // already owned: nothing posted

        Livewire::test(FixedAssets::class)->set('runMonth', '2026-10')->call('runDepreciation')->assertSee('20,000.00');

        Livewire::test(FixedAssets::class)->call('edit', $van->id)->set('cost', '1')->set('name', 'Van 1')->call('save');
        $van->refresh();
        $this->assertSame('Van 1', $van->name);
        $this->assertSame('2400000.00', $van->cost);

        $this->get(route('accounts.assets'))->assertOk()->assertSee('Van 1');
    }
}
