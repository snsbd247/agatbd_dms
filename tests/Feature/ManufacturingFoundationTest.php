<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Factories;
use App\Livewire\Inventory\Warehouses;
use App\Livewire\MasterData\Products;
use App\Livewire\MasterData\Units;
use App\Livewire\Sales\Orders\Create as SalesOrderCreate;
use App\Livewire\Settings\CompanyProfile;
use App\Models\Factory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\Warehouse;
use App\Services\UnitConverter;
use App\Support\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 0 of the ERP plan: units and conversions, product types and packs,
 * factories and warehouse purposes, and the fiscal year.
 */
class ManufacturingFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Unit $kg;
    private Unit $g;
    private Unit $ton;
    private Unit $bag;
    private Unit $litre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->userWithRole('Admin'));

        $this->kg = Unit::create(['name' => 'Kilogram', 'short_code' => 'kg', 'kind' => 'weight']);
        $this->g = Unit::create(['name' => 'Gram', 'short_code' => 'g', 'kind' => 'weight']);
        $this->ton = Unit::create(['name' => 'Ton', 'short_code' => 't', 'kind' => 'weight']);
        $this->bag = Unit::create(['name' => 'Bag', 'short_code' => 'bag', 'kind' => 'count']);
        $this->litre = Unit::create(['name' => 'Litre', 'short_code' => 'L', 'kind' => 'volume']);
    }

    public function test_general_conversions_work_both_ways_and_through_a_shared_unit(): void
    {
        UnitConversion::create(['from_unit_id' => $this->ton->id, 'to_unit_id' => $this->kg->id, 'factor' => 1000]);
        UnitConversion::create(['from_unit_id' => $this->kg->id, 'to_unit_id' => $this->g->id, 'factor' => 1000]);

        $converter = new UnitConverter();
        $this->assertEqualsWithDelta(1000, $converter->factor($this->ton->id, $this->kg->id), 1e-9);
        $this->assertEqualsWithDelta(0.001, $converter->factor($this->kg->id, $this->ton->id), 1e-9);
        $this->assertEqualsWithDelta(1000000, $converter->factor($this->ton->id, $this->g->id), 1e-6);   // via kg
        $this->assertNull($converter->factor($this->kg->id, $this->litre->id));
    }

    public function test_product_packs_come_before_general_conversions(): void
    {
        UnitConversion::create(['from_unit_id' => $this->ton->id, 'to_unit_id' => $this->kg->id, 'factor' => 1000]);
        $urea = $this->product(['unit_id' => $this->kg->id, 'type' => 'finished_good']);
        ProductUnit::create(['product_id' => $urea->id, 'unit_id' => $this->bag->id, 'factor' => 50]);

        $converter = new UnitConverter();
        $this->assertEqualsWithDelta(150, $converter->toBase($urea, 3, $this->bag->id), 1e-9);     // 3 bags
        $this->assertEqualsWithDelta(2000, $converter->toBase($urea, 2, $this->ton->id), 1e-9);    // 2 tons
        $this->assertEqualsWithDelta(4, $converter->fromBase($urea, 200, $this->bag->id), 1e-9);   // 200 kg in bags

        $this->expectException(\RuntimeException::class);
        $converter->toBase($urea, 1, $this->litre->id);
    }

    public function test_units_screen_adds_conversions_only_between_units_of_the_same_kind(): void
    {
        Livewire::test(Units::class)
            ->set('conv_from', $this->kg->id)->set('conv_to', $this->litre->id)->set('conv_factor', '1')
            ->call('addConversion')
            ->assertHasErrors('conv_to');

        Livewire::test(Units::class)
            ->set('conv_from', $this->ton->id)->set('conv_to', $this->kg->id)->set('conv_factor', '1000')
            ->call('addConversion')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('unit_conversions', ['from_unit_id' => $this->ton->id, 'to_unit_id' => $this->kg->id]);

        // The same pair the other way round is refused (it already converts both ways).
        Livewire::test(Units::class)
            ->set('conv_from', $this->kg->id)->set('conv_to', $this->ton->id)->set('conv_factor', '0.001')
            ->call('addConversion')
            ->assertHasErrors('conv_to');
        $this->assertSame(1, UnitConversion::count());
    }

    public function test_product_form_saves_type_shelf_life_and_packs(): void
    {
        $base = $this->product();   // gives company and category

        Livewire::test(Products::class)
            ->call('create')
            ->set('form_company_id', $base->company_id)
            ->set('form_category_id', $base->category_id)
            ->set('form_unit_id', $this->kg->id)
            ->set('sku', 'AG-UREA-50')
            ->set('name', 'Agro Urea')
            ->set('type', 'finished_good')
            ->set('shelf_life_days', '730')
            ->call('addPack')
            ->set('packs.0.unit_id', $this->bag->id)
            ->set('packs.0.factor', '50')
            ->call('save')
            ->assertHasNoErrors();

        $urea = Product::where('sku', 'AG-UREA-50')->firstOrFail();
        $this->assertSame('finished_good', $urea->type);
        $this->assertSame(730, $urea->shelf_life_days);
        $this->assertEqualsWithDelta(50, (float) $urea->units()->first()->factor, 1e-9);

        // Editing: the pack is loaded, can be changed, and removing it deletes the row.
        Livewire::test(Products::class)
            ->call('edit', $urea->id)
            ->assertSet('packs.0.factor', '50')
            ->call('removePack', 0)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(0, $urea->units()->count());

        // A pack in the base unit itself makes no sense.
        Livewire::test(Products::class)
            ->call('edit', $urea->id)
            ->call('addPack')
            ->set('packs.0.unit_id', $this->kg->id)
            ->set('packs.0.factor', '1')
            ->call('save')
            ->assertHasErrors('packs.0.unit_id');
    }

    public function test_product_search_no_longer_ignores_the_other_filters(): void
    {
        $raw = $this->product(['name' => 'Neem cake', 'type' => 'raw_material']);
        $this->product(['name' => 'Neem oil', 'type' => 'finished_good']);

        Livewire::test(Products::class)
            ->set('search', 'Neem')
            ->set('filter_type', 'raw_material')
            ->assertViewHas('products', fn ($p) => $p->pluck('id')->all() === [$raw->id]);
    }

    public function test_raw_materials_cannot_be_sold_and_finished_goods_cannot_be_purchased(): void
    {
        $raw = $this->product(['name' => 'Raw neem', 'type' => 'raw_material']);
        $finished = $this->product(['name' => 'Agro Neem 1L', 'type' => 'finished_good']);
        $this->warehouse();

        Livewire::test(SalesOrderCreate::class)
            ->assertViewHas('products', fn ($p) => $p->pluck('id')->contains($finished->id) && ! $p->pluck('id')->contains($raw->id));

        $this->assertTrue(Product::purchasable()->whereKey($raw->id)->exists());
        $this->assertFalse(Product::purchasable()->whereKey($finished->id)->exists());
        $this->assertFalse(Product::sellable()->whereKey($raw->id)->exists());
    }

    public function test_factories_and_their_stores(): void
    {
        Livewire::test(Factories::class)
            ->call('create')
            ->set('code', 'fac-1')
            ->set('name', 'Gazipur Factory')
            ->call('save')
            ->assertHasNoErrors();

        $factory = Factory::firstOrFail();
        $this->assertSame('FAC-1', $factory->code);

        Livewire::test(Warehouses::class)
            ->call('create')
            ->set('name', 'Gazipur Raw Store')
            ->set('type', 'branch')
            ->set('purpose', 'raw_material')
            ->set('factory_id', $factory->id)
            ->call('save')
            ->assertHasNoErrors();

        $store = Warehouse::where('name', 'Gazipur Raw Store')->firstOrFail();
        $this->assertSame('raw_material', $store->purpose);
        $this->assertSame($factory->id, $store->factory_id);

        // A factory with stores cannot be deleted.
        Livewire::test(Factories::class)->call('delete', $factory->id)->assertHasErrors('delete');
        $this->assertDatabaseHas('factories', ['id' => $factory->id]);

        $this->get(route('inventory.factories'))->assertOk()->assertSee('Gazipur Factory')->assertSee('Gazipur Raw Store');
    }

    public function test_fiscal_year_defaults_to_july_and_can_be_changed(): void
    {
        $fy = FiscalYear::for('2026-11-21');
        $this->assertSame('2026-07-01', $fy['start']->toDateString());
        $this->assertSame('2027-06-30', $fy['end']->toDateString());
        $this->assertSame('2026-27', $fy['label']);
        $this->assertSame('2025-26', FiscalYear::for('2026-06-30')['label']);

        Livewire::test(CompanyProfile::class)
            ->set('name', 'All Good Agro Tech')
            ->set('fiscal_year_start_month', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, FiscalYear::startMonth());
        $this->assertSame('2026', FiscalYear::for('2026-11-21')['label']);
    }
}
