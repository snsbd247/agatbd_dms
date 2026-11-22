<?php

namespace Tests\Feature;

use App\Livewire\Settings\ModuleSettings;
use App\Models\Dealer;
use App\Models\User;
use App\Services\Vat\VatService;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Phase 7a: business type templates and switching modules on and off. */
class ModulesTest extends TestCase
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

    public function test_with_no_type_chosen_everything_is_on(): void
    {
        $this->assertNull(Modules::type());
        foreach (['production', 'hr', 'dealers', 'warranty', 'vat', 'attendance_devices'] as $m) {
            $this->assertTrue(Modules::enabled($m), $m);
        }
        $this->assertTrue(Modules::enabled('costing'));
        $this->assertTrue(Modules::enabled('kits'));
        $this->get(route('settings.modules'))->assertOk();
    }

    public function test_trading_template_has_no_factory(): void
    {
        Livewire::test(ModuleSettings::class)->call('pickType', 'trading')->assertSet('pendingType', 'trading')->call('applyType');

        $this->assertSame('trading', Modules::type());
        $this->assertFalse(Modules::enabled('production'));
        $this->assertFalse(Modules::enabled('warranty'));
        $this->assertTrue(Modules::enabled('dealers'));
        $this->assertTrue(Modules::enabled('hr'));

        // Refused to Admin too: menus, pages and the factory dashboard go away.
        $this->assertFalse($this->admin->can('production.view'));
        $this->get(route('production.orders.index'))->assertForbidden();
        $this->get(route('dashboard.factory'))->assertForbidden();
        $this->get(route('inventory.factories'))->assertForbidden();
        $this->get(route('reports.warranty'))->assertForbidden();
        $this->get(route('dashboard.dealer'))->assertOk()->assertDontSee(route('production.orders.index'));

        // Production cannot be switched on for a trading business.
        Livewire::test(ModuleSettings::class)->call('toggle', 'production')->assertStatus(422);
    }

    public function test_manufacturing_and_both_templates(): void
    {
        Modules::applyTemplate('manufacturing');
        $this->assertTrue(Modules::enabled('production'));
        $this->assertTrue(Modules::enabled('attendance_devices'));
        $this->assertFalse(Modules::enabled('retailer_portal'));

        Modules::applyTemplate('both');
        $this->assertSame('both', Modules::type());
        $this->assertTrue(Modules::enabled('production'));
        $this->assertTrue(Modules::enabled('dealers'));
    }

    public function test_switching_off_takes_dependents_and_on_brings_requirements(): void
    {
        Modules::applyTemplate('manufacturing');

        Livewire::test(ModuleSettings::class)->call('toggle', 'hr');
        $this->assertFalse(Modules::enabled('hr'));
        $this->assertFalse(Modules::enabled('attendance_devices'), 'needs HR');
        $this->get(route('hr.employees.index'))->assertForbidden();
        $this->get(route('hr.devices'))->assertForbidden();
        $this->get('/iclock/cdata?SN=NEW1')->assertOk();
        $this->assertDatabaseCount('attendance_devices', 0);

        Livewire::test(ModuleSettings::class)->call('toggle', 'attendance_devices');
        $this->assertTrue(Modules::enabled('hr'));
        $this->assertTrue(Modules::enabled('attendance_devices'));
        $this->get(route('hr.employees.index'))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'modules.off']);
    }

    public function test_core_modules_cannot_be_switched_off(): void
    {
        Livewire::test(ModuleSettings::class)->call('toggle', 'accounts')->assertStatus(422);
        $this->assertTrue(Modules::enabled('accounts'));
    }

    public function test_vat_module_off_turns_vat_off_but_keeps_the_setting(): void
    {
        \App\Models\Settings\AppSetting::put([VatService::ENABLED => true, VatService::BIN => '001-0101']);
        $this->assertTrue(VatService::enabled());

        Modules::set('vat', false);
        $this->assertFalse(VatService::enabled());
        $this->get(route('reports.vat'))->assertForbidden();

        Modules::set('vat', true);
        $this->assertTrue(VatService::enabled(), 'the saved switch comes back');
    }

    public function test_dealer_logins_follow_the_dealers_module(): void
    {
        $dealer = Dealer::create(['code' => 'D1', 'name' => 'Dealer One', 'phone' => '01700000000', 'active' => true]);
        $user = $this->userWithRole('Dealer', ['dealer_id' => $dealer->id]);
        $this->assertTrue($user->can('portal.access'));

        Modules::set('dealers', false);
        $this->assertFalse($user->can('portal.access'));
        $this->assertFalse($user->can('sales.view'), 'a dealer login can do nothing');
        $this->assertFalse($this->admin->can('dealers.view'));
    }

    public function test_only_admins_reach_the_page(): void
    {
        $this->actingAs($this->userWithRole('Manager'))->get(route('settings.modules'))->assertForbidden();
    }
}
