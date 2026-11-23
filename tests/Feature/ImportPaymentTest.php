<?php

namespace Tests\Feature;

use App\Livewire\Import\Show as ImportShow;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\Company;
use App\Models\Import\ImportShipment;
use App\Services\Import\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Paying an import's foreign supplier at a different rate: exchange gain or loss. */
class ImportPaymentTest extends TestCase
{
    use RefreshDatabase;

    private int $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->actingAs($this->userWithRole('Admin'));
        $this->bank = Account::bySystemKey('bank')->id;
    }

    private function bal(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    /** $1,000 of goods at ৳120, with a ৳12,000 LC margin, received. */
    private function receivedImport(): ImportShipment
    {
        $p = $this->product(['type' => 'raw_material']);
        $s = ImportShipment::create(['number' => 'IMP-9', 'company_id' => Company::firstOrCreate(['code' => 'SUP'], ['name' => 'Foreign Co'])->id,
            'warehouse_id' => $this->warehouse()->id, 'currency' => 'USD', 'exchange_rate' => 120, 'allocation_basis' => 'value', 'status' => 'draft']);
        $page = Livewire::test(ImportShow::class, ['shipment' => $s]);
        $page->set('item_product_id', $p->id)->set('item_quantity', '1000')->set('item_price', '1.00')->call('addItem');
        $page->set('lc_no', 'LC-9')->set('lc_margin', '12000')->set('lc_bank', $this->bank)->call('openLc')->assertHasNoErrors();
        $svc = app(ImportService::class);
        $svc->shipped($s->fresh(), 'BL-1', today()->toDateString(), null);
        $svc->arrived($s->fresh(), today()->toDateString(), 'Chattogram');
        Livewire::test(ImportShow::class, ['shipment' => $s->fresh()])->call('receive')->assertHasNoErrors();

        return $s->fresh();
    }

    public function test_paying_at_a_higher_rate_books_an_exchange_loss(): void
    {
        $s = $this->receivedImport();
        $this->assertSame(900.0, $s->foreignOutstanding());                 // $100 covered by the margin
        $this->assertSame(-108000.0, $this->bal('ap'));

        Livewire::test(ImportShow::class, ['shipment' => $s])
            ->set('pay_foreign', '900')->set('pay_bdt', '110700')->set('pay_from', $this->bank)   // ৳123
            ->call('settle')->assertHasNoErrors();

        $this->assertSame(0.0, $this->bal('ap'));
        $this->assertSame(2700.0, $this->bal('fx_gain_loss'));               // debit = loss
        $this->assertSame(0.0, $s->fresh()->foreignOutstanding());
    }

    public function test_paying_at_a_lower_rate_books_a_gain_and_overpaying_is_refused(): void
    {
        $s = $this->receivedImport();
        app(ImportService::class)->settle($s, today()->toDateString(), 500, 59000, $this->bank);   // ৳118
        $this->assertSame(-1000.0, $this->bal('fx_gain_loss'));             // credit = gain
        $this->assertSame(400.0, $s->fresh()->foreignOutstanding());

        Livewire::test(ImportShow::class, ['shipment' => $s->fresh()])
            ->set('pay_foreign', '401')->set('pay_bdt', '48000')->set('pay_from', $this->bank)
            ->call('settle')->assertHasErrors('pay_foreign');
    }
}
