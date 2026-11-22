<?php

namespace App\Livewire\Settings\Users;

use App\Models\Settings\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Users')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter_role = '';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $form_role = 'Manager';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_role']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('settings.manage');
        $this->reset(['editingId', 'name', 'email', 'password']);
        $this->form_role = 'Manager';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('settings.manage');
        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->form_role = $user->getRoleNames()->first() ?? 'Manager';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => $this->editingId ? 'nullable|string|min:8' : 'required|string|min:8',
            'form_role' => 'required|in:Admin,Manager,Accountant,HR Manager,Factory Manager',
        ]);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $original = collect($user->getOriginal())->except('password')->all();

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
            ]);
            $user->syncRoles([$data['form_role']]);

            $changes = collect($user->getChanges())->except('password')->all();
            if ($data['password']) {
                $changes['password'] = '(changed)';
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'user.updated',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'old_values' => $original,
                'new_values' => $changes + ['role' => $data['form_role']],
                'ip_address' => request()->ip(),
            ]);
        } else {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->syncRoles([$data['form_role']]);   // a fresh user has exactly this role

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'user.created',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'old_values' => null,
                'new_values' => ['name' => $data['name'], 'email' => $data['email'], 'role' => $data['form_role']],
                'ip_address' => request()->ip(),
            ]);
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $this->authorize('settings.manage');

        if ($id === Auth::id()) {
            $this->addError('delete', 'You cannot delete your own account.');

            return;
        }

        $user = User::findOrFail($id);

        if ($user->hasRole('Admin') && User::role('Admin')->whereNull('dealer_id')->count() <= 1) {
            $this->addError('delete', __('Cannot delete the last remaining Admin account.'));

            return;
        }

        $attributes = $user->only(['name', 'email']) + ['role' => $user->getRoleNames()->first()];
        $user->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'user.deleted',
            'auditable_type' => User::class,
            'auditable_id' => $id,
            'old_values' => $attributes,
            'new_values' => null,
            'ip_address' => request()->ip(),
        ]);
    }

    public function render()
    {
        $users = User::role(['Admin', 'Manager', 'Accountant', 'HR Manager', 'Factory Manager'])->whereNull('dealer_id')
            ->with('roles')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"))
            ->when($this->filter_role, fn ($q) => $q->role($this->filter_role))
            ->latest('id')
            ->paginate(10);

        $summary = [
            'total' => User::role(['Admin', 'Manager', 'Accountant', 'HR Manager', 'Factory Manager'])->whereNull('dealer_id')->count(),
            'dealers' => User::role('Admin')->whereNull('dealer_id')->count(),
            'managers' => User::role('Manager')->count(),
            'new_this_month' => User::role(['Admin', 'Manager', 'Accountant', 'HR Manager', 'Factory Manager'])->whereNull('dealer_id')->where('created_at', '>=', today()->startOfMonth())->count(),
        ];

        return view('livewire.settings.users.index', [
            'users' => $users,
            'summary' => $summary,
        ]);
    }
}
