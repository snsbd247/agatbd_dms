<?php

namespace Tests\Feature;

use App\Livewire\Costing\Sheet as CostSheetPage;
use App\Livewire\Import\Show as ImportShow;
use App\Livewire\Kits\Index as KitsPage;
use App\Livewire\Setup\Wizard;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Costing\CostSheet;
use App\Models\Import\ImportShipment;
use App\Models\Kits\AssemblyOrder;
use App\Models\Production\Bom;
use App\Models\Settings\AppSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Costing\CostingService;
use App\Services\Import\ImportService;
use App\Services\Kits\AssemblyService;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Phase 7b–7d: cost sheets and price tiers, imports at landed cost, kits and repacking, first-run setup. */
class Phase7Test extends TestCase
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

    private function balance(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    // ---- costing ---------------------------------------------------------

    public function test_a_purchase_cost_sheet_sets_the_three_prices_when_approved(): void
    {
        $p = $this->product(['sale_price' => 100, 'purchase_price' => 70, 'type' => 'trading']);
        $this->receive($p, 10, 'B1', 80);   // last buying cost 80

        $c = Livewire::test(CostSheetPage::class, ['product' => $p->id])
            ->assertSet('basis', 'purchase')
            ->assertSet('materials.0.unit_cost', 80.0);

        // Only one overhead: 10% of 80 = 8 → cost 88. Margins 10 / 10 / 10.
        $c->set('overheads', [['cost_head_id' => null, 'description' => 'Carriage', 'basis' => 'percent', 'rate' => 10]])
            ->set('company_margin', '10')->set('dealer_margin', '10')->set('retailer_margin', '10')
            ->call('suggest')
            ->assertSet('dealer_price', '97')     // 88 × 1.1 = 96.8
            ->assertSet('retailer_price', '107')  // 97 × 1.1 = 106.7
            ->assertSet('mrp', '118')             // 107 × 1.1 = 117.7
            ->call('save')->assertHasNoErrors();

        $sheet = CostSheet::firstOrFail();
        $this->assertSame('draft', $sheet->status);
        $this->assertEqualsWithDelta(88, (float) $sheet->total_cost, 0.001);
        $this->assertSame(100.0, (float) $p->fresh()->sale_price, 'not used before approval');

        Livewire::test(CostSheetPage::class, ['sheet' => $sheet])->call('approve')->assertHasNoErrors();
        $p->refresh();
        $this->assertSame('applied', $sheet->fresh()->status);
        $this->assertSame(107.0, (float) $p->sale_price);
        $this->assertSame(97.0, (float) $p->dealer_price);
        $this->assertSame(118.0, (float) $p->mrp);
        $this->assertDatabaseHas('product_prices', ['product_id' => $p->id, 'cost_sheet_id' => $sheet->id]);

        // A dealer pays the dealer price, a retailer the retailer price.
        $dealerAccount = $this->retailer(['account_dealer_id' => \App\Models\Dealer::create(['code' => 'D1', 'name' => 'D', 'phone' => '1', 'active' => true])->id]);
        $this->assertSame(97.0, $p->priceFor($dealerAccount));
        $this->assertSame(107.0, $p->priceFor($this->retailer()));

        $this->get(route('costing.sheets.pdf', $sheet))->assertOk();
        $this->get(route('costing.index'))->assertOk()->assertSee($p->name);
    }

    public function test_a_future_dated_sheet_waits_and_the_daily_job_applies_it(): void
    {
        $p = $this->product(['sale_price' => 100, 'purchase_price' => 70]);
        $svc = app(CostingService::class);
        $m = $svc->materials($p, 'purchase');
        $sheet = $svc->save($p, ['basis' => 'purchase', 'bom_id' => null, 'materials' => $m['lines'], 'overheads' => [],
            'company_margin' => 0, 'dealer_margin' => 0, 'retailer_margin' => 0, 'dealer_price' => 90, 'retailer_price' => 120, 'mrp' => 130,
            'effective_from' => today()->addDays(3)->toDateString(), 'first_supply_date' => null, 'notes' => null]);
        $svc->approve($sheet);
        $this->assertSame('approved', $sheet->fresh()->status);
        $this->assertSame(100.0, (float) $p->fresh()->sale_price);

        $this->travel(3)->days();
        $this->artisan('costing:apply-due')->assertSuccessful();
        $this->assertSame(120.0, (float) $p->fresh()->sale_price);
    }

    public function test_a_formula_sheet_uses_materials_with_wastage_and_prints_mushak_4_3(): void
    {
        $fg = $this->product(['type' => 'finished_good', 'name' => 'Mix 50 kg', 'hs_code' => '3105.20.00']);
        $urea = $this->product(['type' => 'raw_material', 'name' => 'Urea', 'purchase_price' => 20]);
        $bag = $this->product(['type' => 'packaging', 'name' => 'Bag', 'purchase_price' => 15]);
        $bom = Bom::create(['product_id' => $fg->id, 'kind' => 'production', 'version' => 1, 'output_quantity' => 10, 'expected_yield' => 100, 'is_default' => true, 'active' => true]);
        $bom->items()->create(['product_id' => $urea->id, 'quantity' => 500, 'wastage_percent' => 2]);   // 50 kg per bag + 2%
        $bom->items()->create(['product_id' => $bag->id, 'quantity' => 10, 'wastage_percent' => 0]);

        $svc = app(CostingService::class);
        $this->assertSame(['formula', 'purchase'], $svc->bases($fg));
        $m = $svc->materials($fg, 'formula');
        $this->assertEqualsWithDelta(51, $m['lines'][0]['quantity'], 0.0001);
        $this->assertEqualsWithDelta(51 * 20 + 15, array_sum(array_column($m['lines'], 'amount')), 0.001);

        $sheet = $svc->save($fg, ['basis' => 'formula', 'bom_id' => $m['bom_id'], 'materials' => $m['lines'],
            'overheads' => [['cost_head_id' => null, 'description' => 'Labour', 'basis' => 'amount', 'rate' => 35]],
            'company_margin' => 0, 'dealer_margin' => 0, 'retailer_margin' => 0, 'dealer_price' => 1200, 'retailer_price' => 1300, 'mrp' => 1400,
            'effective_from' => today()->toDateString(), 'first_supply_date' => null, 'notes' => null]);
        $this->assertEqualsWithDelta(1070, (float) $sheet->total_cost, 0.001);

        $this->get(route('costing.sheets.pdf', $sheet))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_prices_must_rise_from_dealer_to_mrp(): void
    {
        $p = $this->product(['purchase_price' => 50]);
        Livewire::test(CostSheetPage::class, ['product' => $p->id])
            ->set('dealer_price', '100')->set('retailer_price', '90')->set('mrp', '120')
            ->call('save')->assertHasErrors('retailer_price');
    }

    // ---- import ----------------------------------------------------------

    public function test_an_import_enters_stock_at_landed_cost_with_the_right_vouchers(): void
    {
        $supplier = Company::create(['code' => 'CN1', 'name' => 'Qingdao Chem', 'country' => 'China', 'currency' => 'USD', 'active' => true]);
        $wh = $this->warehouse();
        $mop = $this->product(['type' => 'raw_material', 'name' => 'MOP']);
        $zinc = $this->product(['type' => 'raw_material', 'name' => 'Zinc']);
        $bank = Account::bySystemKey('bank')->id;

        $s = ImportShipment::create(['number' => 'IMP-1', 'company_id' => $supplier->id, 'warehouse_id' => $wh->id, 'currency' => 'USD',
            'exchange_rate' => 120, 'allocation_basis' => 'value', 'status' => 'draft']);
        $page = Livewire::test(ImportShow::class, ['shipment' => $s]);
        $page->set('item_product_id', $mop->id)->set('item_quantity', '1000')->set('item_price', '0.30')->call('addItem');   // $300 = ৳36,000
        $page->set('item_product_id', $zinc->id)->set('item_quantity', '100')->set('item_price', '1.00')->call('addItem');   // $100 = ৳12,000

        $page->set('lc_no', 'LC-77')->set('lc_margin', '4800')->set('lc_bank', $bank)->call('openLc')->assertHasNoErrors();
        $this->assertSame(4800.0, $this->balance('lc_margin'));

        $svc = app(ImportService::class);
        $svc->addCost($s->fresh(), 'cd', 4000, today()->toDateString(), $bank, null);    // into cost
        $svc->addCost($s->fresh(), 'cnf', 2000, today()->toDateString(), $bank, null);   // into cost
        $svc->addCost($s->fresh(), 'vat', 7200, today()->toDateString(), $bank, null);   // claimed back
        $svc->addCost($s->fresh(), 'ait', 2400, today()->toDateString(), $bank, null);   // claimed back
        $this->assertSame(6000.0, $this->balance('goods_in_transit'));
        $this->assertSame(7200.0, $this->balance('vat_input'));
        $this->assertSame(2400.0, $this->balance('advance_tax'));

        // 6000 shared by value: MOP 36000/48000 = 4500, zinc 1500.
        $alloc = $svc->allocation($s->fresh());
        $this->assertEqualsWithDelta(40.5, $alloc[$s->items()->where('product_id', $mop->id)->value('id')]['unit'], 0.0001);   // (36000+4500)/1000
        $this->assertEqualsWithDelta(135, $alloc[$s->items()->where('product_id', $zinc->id)->value('id')]['unit'], 0.0001);    // (12000+1500)/100

        Livewire::test(ImportShow::class, ['shipment' => $s->fresh()])->call('receive')->assertHasNoErrors();

        $this->assertSame('received', $s->fresh()->status);
        $this->assertSame(40.5, (float) Batch::where('product_id', $mop->id)->value('cost_price'));
        $this->assertSame(54000.0, $this->balance('inventory'));       // 48000 + 6000
        $this->assertSame(0.0, $this->balance('goods_in_transit'));
        $this->assertSame(0.0, $this->balance('lc_margin'), 'set against the supplier');
        $this->assertSame(-43200.0, $this->balance('ap'), '48000 owed less the 4800 margin');

        // Closed: no more costs; costing can use the landed cost.
        $this->expectException(\RuntimeException::class);
        try {
            $this->assertSame(['import', 'purchase'], app(CostingService::class)->bases($mop));
        } finally {
            $svc->addCost($s->fresh(), 'port', 100, today()->toDateString(), $bank, null);
        }
    }

    public function test_removing_an_import_cost_reverses_it(): void
    {
        $supplier = Company::create(['code' => 'IN1', 'name' => 'Delhi Seeds', 'currency' => 'USD', 'active' => true]);
        $s = ImportShipment::create(['number' => 'IMP-2', 'company_id' => $supplier->id, 'warehouse_id' => $this->warehouse()->id, 'currency' => 'USD',
            'exchange_rate' => 120, 'status' => 'draft']);
        $svc = app(ImportService::class);
        $cost = $svc->addCost($s, 'cnf', 1000, today()->toDateString(), Account::bySystemKey('cash')->id, null);
        $this->assertSame(1000.0, $this->balance('goods_in_transit'));
        $svc->removeCost($cost);
        $this->assertSame(0.0, $this->balance('goods_in_transit'));
        $this->assertSame(0, $s->costs()->count());
    }

    // ---- kits & repacking --------------------------------------------------

    public function test_repacking_turns_bulk_into_packets_at_cost_plus_labour(): void
    {
        $wh = $this->warehouse();
        $bulk = $this->product(['type' => 'trading', 'name' => 'Seed bulk (kg)']);
        $pouch = $this->product(['type' => 'packaging', 'name' => 'Pouch']);
        $packet = $this->product(['type' => 'trading', 'name' => 'Seed 1 kg packet', 'sku' => 'SEED1']);
        app(\App\Services\InventoryService::class)->receiveStock(productId: $bulk->id, warehouseId: $wh->id, batchNo: 'BULK-1',
            expiryDate: today()->addMonths(8)->toDateString(), costPrice: 200, quantity: 100, reference: $bulk);
        $this->receive($pouch, 100, 'P-1', 3);

        Livewire::test(KitsPage::class)->call('newRecipe')
            ->set('kind', 'repack')->set('product_id', $packet->id)->set('output_quantity', '50')
            ->set('items', [['product_id' => (string) $bulk->id, 'quantity' => '50', 'wastage_percent' => '0'], ['product_id' => (string) $pouch->id, 'quantity' => '50', 'wastage_percent' => '0']])
            ->call('saveRecipe')->assertHasNoErrors();
        $bom = Bom::where('kind', 'repack')->firstOrFail();

        Livewire::test(KitsPage::class)->call('newAssembly', $bom->id)
            ->set('quantity', '50')->set('warehouse_id', $wh->id)->set('labour', '250')
            ->call('assemble')->assertHasNoErrors();

        $o = AssemblyOrder::firstOrFail();
        $this->assertEqualsWithDelta(10150, (float) $o->material_cost, 0.001);   // 50 × 200 + 50 × 3
        $this->assertEqualsWithDelta(208, (float) $o->unit_cost, 0.0001);        // (10150 + 250) / 50
        $out = Batch::find($o->output_batch_id);
        $this->assertSame(50.0, (float) $out->quantity_available);
        $this->assertSame(today()->addMonths(8)->toDateString(), $out->expiry_date->toDateString(), 'expires with the bulk');
        $this->assertSame(50.0, (float) Batch::where('batch_no', 'BULK-1')->value('quantity_available'));
        $this->assertSame(250.0, $this->balance('inventory'), 'only the labour adds value');

        // Not enough left for another 60.
        $this->expectException(\RuntimeException::class);
        app(AssemblyService::class)->assemble($bom, 60, $wh->id, today()->toDateString());
    }

    public function test_kit_recipes_stay_out_of_production_formulas(): void
    {
        $kit = $this->product(['type' => 'trading', 'name' => 'Combo pack']);
        Bom::create(['product_id' => $kit->id, 'kind' => 'kit', 'version' => 1, 'output_quantity' => 1, 'expected_yield' => 100, 'is_default' => true, 'active' => true]);
        $this->get(route('production.formulas'))->assertOk()->assertDontSee('Combo pack');
        $this->get(route('kits.index'))->assertOk()->assertSee('Combo pack');
    }

    // ---- modules & setup ---------------------------------------------------

    public function test_new_modules_follow_the_templates(): void
    {
        Modules::applyTemplate('manufacturing');
        $this->assertTrue(Modules::enabled('costing'));
        $this->assertTrue(Modules::enabled('import'));
        $this->assertFalse(Modules::enabled('kits'), 'kits are for trading');
        $this->get(route('kits.index'))->assertForbidden();

        Modules::applyTemplate('trading');
        $this->assertTrue(Modules::enabled('kits'));
        Modules::set('import', false);
        $this->get(route('import.index'))->assertForbidden();
    }

    public function test_a_fresh_install_starts_with_the_setup_wizard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('settings.setup'));

        Livewire::test(Wizard::class)
            ->set('type', 'both')->call('next')
            ->set('name', 'Agro Ltd')->call('next')
            ->set('vat', true)->set('bin', '')->call('next')->assertHasErrors('bin')
            ->set('bin', '000111222-0101')->call('next')
            ->set('warehouse', 'Dhaka Depot')->set('factory', 'Gazipur Factory')
            ->call('finish')->assertRedirect(route('dashboard'));

        $this->assertSame('both', Modules::type());
        $this->assertTrue(\App\Services\Vat\VatService::enabled());
        $this->assertTrue(Warehouse::where('name', 'Dhaka Depot')->exists());
        $this->assertSame(3, Warehouse::whereNotNull('factory_id')->count());
        $this->assertFalse(Wizard::needed());
        $this->get('/dashboard')->assertRedirect(route('dashboard.dealer'));
    }
}
