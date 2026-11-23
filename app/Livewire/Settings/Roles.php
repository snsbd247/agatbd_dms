<?php

namespace App\Livewire\Settings;

use App\Models\Settings\AuditLog;
use App\Support\Roles as RoleList;
use Database\Seeders\PermissionSeeder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Roles and what each may do. Built-in roles are shown read-only (every
 * update re-applies them); a custom role ("Store Keeper", "Cashier" …) can
 * start empty or as a copy of a built-in one, and is given to staff logins
 * under Users.
 */
#[Layout('layouts.app')]
#[Title('Roles & Permissions')]
class Roles extends Component
{
    public ?int $selectedId = null;

    public bool $showModal = false;
    public ?int $editingId = null;
    public string $name = '';

    /** @var array<int, string> */
    public array $permissions = [];

    public function mount(): void
    {
        $this->authorize('settings.manage');
        abort_if(auth()->user()->dealer_id, 403);
        $this->selectedId = Role::where('name', 'Manager')->value('id');
    }

    /** permission prefix => [permission => action label] */
    public static function groups(): array
    {
        $groups = [];
        foreach (PermissionSeeder::PERMISSIONS as $p) {
            [$module, $action] = array_pad(explode('.', $p, 2), 2, '');
            $groups[$module][$p] = ucfirst(str_replace(['-', '_'], ' ', $action));
        }

        return $groups;
    }

    public function create(?int $copyFrom = null): void
    {
        $this->authorize('settings.manage');
        $this->reset(['editingId', 'name']);
        $this->permissions = $copyFrom ? Role::findOrFail($copyFrom)->permissions->pluck('name')->all() : [];
        $this->name = $copyFrom ? __('Copy of :r', ['r' => Role::find($copyFrom)->name]) : '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('settings.manage');
        $role = Role::findOrFail($id);
        abort_if(RoleList::isBuiltIn($role->name), 403);
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->permissions = $role->permissions->pluck('name')->all();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function toggleGroup(string $module): void
    {
        $all = array_keys(self::groups()[$module] ?? []);
        $this->permissions = count(array_intersect($all, $this->permissions)) === count($all)
            ? array_values(array_diff($this->permissions, $all))
            : array_values(array_unique(array_merge($this->permissions, $all)));
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $this->name = trim($this->name);
        $this->validate([
            'name' => ['required', 'string', 'max:60', \Illuminate\Validation\Rule::unique('roles', 'name')->ignore($this->editingId)],
            'permissions' => 'array',
            'permissions.*' => ['string', \Illuminate\Validation\Rule::in(PermissionSeeder::PERMISSIONS)],
        ]);
        if (RoleList::isBuiltIn($this->name)) {
            $this->addError('name', __('That name belongs to a built-in role.'));

            return;
        }

        $role = $this->editingId ? Role::findOrFail($this->editingId) : Role::create(['name' => $this->name, 'guard_name' => 'web']);
        abort_if(RoleList::isBuiltIn($role->name), 403);
        $before = $role->permissions->pluck('name')->all();
        $role->update(['name' => $this->name]);
        $role->syncPermissions($this->permissions);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $this->editingId ? 'role.updated' : 'role.created',
            'new_values' => ['role' => $role->name, 'added' => array_values(array_diff($this->permissions, $before)), 'removed' => array_values(array_diff($before, $this->permissions))],
            'ip_address' => request()->ip(),
        ]);

        $this->selectedId = $role->id;
        $this->showModal = false;
        session()->flash('success', __('Role :r saved.', ['r' => $role->name]));
    }

    public function delete(int $id): void
    {
        $this->authorize('settings.manage');
        $role = Role::findOrFail($id);
        abort_if(RoleList::isBuiltIn($role->name), 403);
        if ($role->users()->exists()) {
            session()->flash('error', __('Users still have this role. Give them another role first.'));

            return;
        }
        $role->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->selectedId = Role::where('name', 'Manager')->value('id');
    }

    public function render()
    {
        $roles = Role::where('guard_name', 'web')->withCount('users')->with('permissions')->get()
            ->sortBy(fn ($r) => [RoleList::isBuiltIn($r->name) ? 0 : 1, $r->name])->values();

        return view('livewire.settings.roles', [
            'roles' => $roles,
            'selected' => $roles->firstWhere('id', $this->selectedId) ?? $roles->first(),
            'groups' => self::groups(),
        ]);
    }
}
