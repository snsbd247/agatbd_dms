<?php

namespace App\Livewire\Retailers;

use App\Models\Area;
use App\Models\Representative;
use App\Models\Retailer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Retailers')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter_status = '';
    public ?int $filter_area_id = null;
    public ?int $filter_representative_id = null;

    public bool $showModal = false;
    public ?int $editingId = null;
    public ?string $linkedEmail = null;
    public string $currentDueDisplay = '0';

    public string $code = '';
    public string $name = '';
    public string $shop_name = '';
    public string $phone = '';
    public string $address = '';
    public ?int $form_area_id = null;
    public ?int $form_representative_id = null;
    public string $credit_limit = '0';
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
        $this->reset(['search', 'filter_status', 'filter_area_id', 'filter_representative_id']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('retailers.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('retailers.manage');
        $retailer = Retailer::findOrFail($id);
        $this->editingId = $retailer->id;
        $this->code = $retailer->code;
        $this->name = $retailer->name;
        $this->shop_name = (string) $retailer->shop_name;
        $this->phone = $retailer->phone;
        $this->address = (string) $retailer->address;
        $this->form_area_id = $retailer->area_id;
        $this->form_representative_id = $retailer->representative_id;
        $this->credit_limit = (string) $retailer->credit_limit;
        $this->active = $retailer->active;
        $this->linkedEmail = $retailer->user?->email;
        $this->currentDueDisplay = (string) $retailer->current_due;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('retailers.manage');

        $data = $this->validate([
            'code' => 'required|string|max:50|unique:retailers,code,'.($this->editingId ?? 'NULL').',id',
            'name' => 'required|string|max:255',
            'shop_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'nullable|string|max:255',
            'form_area_id' => 'required|exists:areas,id',
            'form_representative_id' => 'nullable|exists:representatives,id',
            'credit_limit' => 'required|numeric|min:0',
            'active' => 'boolean',
        ]);

        $data['area_id'] = $data['form_area_id'];
        $data['representative_id'] = $data['form_representative_id'];
        unset($data['form_area_id'], $data['form_representative_id']);

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
                ]);
                $user->syncRoles(['Retailer']);   // a fresh user has exactly this role
                $userId = $user->id;
            }

            $retailer = Retailer::updateOrCreate(['id' => $this->editingId], $data);

            if ($userId) {
                $retailer->update(['user_id' => $userId]);
            }
        });

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('retailers.manage');
        Retailer::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'linkedEmail', 'code', 'name', 'shop_name', 'phone', 'address',
            'form_area_id', 'form_representative_id', 'create_login', 'login_email', 'login_password',
        ]);
        $this->credit_limit = '0';
        $this->active = true;
        $this->resetErrorBag();
    }

    private function filteredQuery()
    {
        return Retailer::query()
            ->with(['area', 'representative'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
            ->when($this->filter_status !== '', fn ($q) => $q->where('active', $this->filter_status === 'active'))
            ->when($this->filter_area_id, fn ($q) => $q->where('area_id', $this->filter_area_id))
            ->when($this->filter_representative_id, fn ($q) => $q->where('representative_id', $this->filter_representative_id));
    }

    public function render()
    {
        $retailers = $this->filteredQuery()->orderBy('name')->paginate(10);

        $summary = [
            'total' => Retailer::count(),
            'active' => Retailer::where('active', true)->count(),
            'inactive' => Retailer::where('active', false)->count(),
            'total_due' => Retailer::sum('current_due'),
        ];

        return view('livewire.retailers.index', [
            'retailers' => $retailers,
            'areas' => Area::orderBy('name')->get(),
            'representatives' => Representative::orderBy('name')->get(),
            'summary' => $summary,
        ]);
    }
}
