<?php

namespace Tests\Feature;

use App\Livewire\Settings\Roles;
use App\Livewire\Settings\Users\Index as UsersPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Custom roles: made from scratch or copied, given to staff, built-ins protected. */
class CustomRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->userWithRole('Admin'));
    }

    public function test_a_custom_role_is_made_and_given_to_a_user(): void
    {
        Livewire::test(Roles::class)->call('create')
            ->set('name', 'Store Keeper')->set('permissions', ['inventory.view', 'inventory.manage', 'purchase.view'])
            ->call('save')->assertHasNoErrors();

        $role = Role::findByName('Store Keeper');
        $this->assertSame(3, $role->permissions()->count());

        Livewire::test(UsersPage::class)->call('create')
            ->set('name', 'Karim')->set('email', 'karim@example.com')->set('password', 'secret123')
            ->set('form_role', 'Store Keeper')->call('save')->assertHasNoErrors();

        $karim = User::where('email', 'karim@example.com')->first();
        $this->assertTrue($karim->can('inventory.manage'));
        $this->assertFalse($karim->can('accounts.view'));
        $this->get(route('settings.roles'))->assertOk()->assertSee('Store Keeper');
    }

    public function test_copying_a_built_in_role_and_toggling_a_group(): void
    {
        $manager = Role::findByName('Manager');
        $c = Livewire::test(Roles::class)->call('create', $manager->id);
        $this->assertSame($manager->permissions->count(), count($c->get('permissions')));

        $c->set('name', 'Area Manager')->call('toggleGroup', 'hr');
        $hasHr = collect($c->get('permissions'))->contains(fn ($p) => str_starts_with($p, 'hr.'));
        $c->call('save')->assertHasNoErrors();
        $this->assertSame($hasHr, Role::findByName('Area Manager')->permissions->contains(fn ($p) => str_starts_with($p->name, 'hr.')));
    }

    public function test_built_in_roles_cannot_be_edited_renamed_into_or_deleted(): void
    {
        $manager = Role::findByName('Manager');
        Livewire::test(Roles::class)->call('edit', $manager->id)->assertForbidden();
        Livewire::test(Roles::class)->call('delete', $manager->id)->assertForbidden();
        Livewire::test(Roles::class)->call('create')->set('name', 'Admin')->call('save')->assertHasErrors('name');
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        $this->userWithRole('Cashier');
        Livewire::test(Roles::class)->call('delete', $role->id)->assertSee('still have this role');
        $this->assertNotNull(Role::find($role->id));
    }
}
