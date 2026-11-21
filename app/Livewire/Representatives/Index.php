<?php

namespace App\Livewire\Representatives;

use App\Models\Area;
use App\Models\Representative;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Representatives')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter_status = '';
    public ?int $filter_area_id = null;

    public bool $showModal = false;
    public ?int $editingId = null;
    public ?string $linkedEmail = null;

    public string $employee_code = '';
    public string $name = '';
    public string $phone = '';
    public ?int $form_area_id = null;
    public string $salary_base = '0';
    public string $commission_rate = '0';
    public string $target_amount = '0';
    public bool $active = true;

    public bool $create_login = false;
    public string $login_email = '';
    public string $login_password = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_status', 'filter_area_id']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('representatives.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('representatives.manage');
        $representative = Representative::findOrFail($id);
        $this->editingId = $representative->id;
        $this->employee_code = $representative->employee_code;
        $this->name = $representative->name;
        $this->phone = (string) $representative->phone;
        $this->form_area_id = $representative->area_id;
        $this->salary_base = (string) $representative->salary_base;
        $this->commission_rate = (string) $representative->commission_rate;
        $this->target_amount = (string) $representative->target_amount;
        $this->active = $representative->active;
        $this->linkedEmail = $representative->user?->email;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('representatives.manage');

        $data = $this->validate([
            'employee_code' => 'required|string|max:50|unique:representatives,employee_code,'.($this->editingId ?? 'NULL').',id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'form_area_id' => 'nullable|exists:areas,id',
            'salary_base' => 'required|numeric|min:0',
            'commission_rate' => 'required|numeric|min:0',
            'target_amount' => 'required|numeric|min:0',
            'active' => 'boolean',
        ]);

        $data['area_id'] = $data['form_area_id'];
        unset($data['form_area_id']);

        if (! $this->editingId && $this->create_login) {
            $this->validate([
                'login_email' => 'required|email|unique:users,email',
                'login_password' => 'required|string|min:8',
            ]);
        }

        DB::transaction(function () use ($data) {
            $userId = null;

            if (! $this->editingId && $this->create_login) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $this->login_email,
                    'password' => $this->login_password,
                    'dealer_id' => \App\Support\Tenant::dealerId(),   // a dealer's own staff work in the dealer's panel
                ]);
                $user->syncRoles(['Sales Representative']);   // a fresh user has exactly this role
                $userId = $user->id;
            }

            $representative = Representative::updateOrCreate(['id' => $this->editingId], $data);

            if ($userId) {
                $representative->update(['user_id' => $userId]);
            }
        });

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('representatives.manage');
        Representative::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'linkedEmail', 'employee_code', 'name', 'phone', 'form_area_id',
            'create_login', 'login_email', 'login_password',
        ]);
        $this->salary_base = '0';
        $this->commission_rate = '0';
        $this->target_amount = '0';
        $this->active = true;
        $this->resetErrorBag();
    }

    private function filteredQuery()
    {
        return Representative::query()
            ->with('area')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('employee_code', 'like', "%{$this->search}%"))
            ->when($this->filter_status !== '', fn ($q) => $q->where('active', $this->filter_status === 'active'))
            ->when($this->filter_area_id, fn ($q) => $q->where('area_id', $this->filter_area_id));
    }

    public function render()
    {
        $representatives = $this->filteredQuery()->orderBy('name')->paginate(10);

        $summary = [
            'total' => Representative::count(),
            'active' => Representative::where('active', true)->count(),
            'inactive' => Representative::where('active', false)->count(),
            'total_target' => Representative::sum('target_amount'),
        ];

        return view('livewire.representatives.index', [
            'representatives' => $representatives,
            'areas' => Area::orderBy('name')->get(),
            'summary' => $summary,
        ]);
    }
}
