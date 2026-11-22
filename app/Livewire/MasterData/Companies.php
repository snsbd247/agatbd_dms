<?php

namespace App\Livewire\MasterData;

use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Companies')]
class Companies extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $code = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $country = '';
    public string $currency = 'BDT';
    public bool $active = true;

    public string $search = '';
    public string $filter_status = '';
    public string $filter_territory = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_status', 'filter_territory']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('master-data.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('master-data.manage');
        $company = Company::findOrFail($id);
        $this->editingId = $company->id;
        $this->name = $company->name;
        $this->code = $company->code;
        $this->phone = (string) $company->phone;
        $this->email = (string) $company->email;
        $this->address = (string) $company->address;
        $this->country = (string) $company->country;
        $this->currency = (string) ($company->currency ?: 'BDT');
        $this->active = $company->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('master-data.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:companies,code,'.($this->editingId ?? 'NULL').',id',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:60',
            'currency' => 'required|string|size:3',
            'active' => 'boolean',
        ]);
        $data['currency'] = strtoupper($data['currency']);
        $data['country'] = $data['country'] ?: null;

        Company::updateOrCreate(['id' => $this->editingId], $data);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('master-data.manage');
        Company::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'phone', 'email', 'address', 'country', 'currency']);
        $this->active = true;
        $this->resetErrorBag();
    }

    private function filteredQuery()
    {
        return Company::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->when($this->filter_status !== '', fn ($q) => $q->where('active', $this->filter_status === 'active'))
            ->when($this->filter_territory, fn ($q) => $q->where('address', $this->filter_territory));
    }

    public function render()
    {
        $companies = $this->filteredQuery()->orderBy('name')->paginate(10);

        $territories = Company::whereNotNull('address')->where('address', '!=', '')->distinct()->orderBy('address')->pluck('address');

        $summary = [
            'total' => Company::count(),
            'active' => Company::where('active', true)->count(),
            'inactive' => Company::where('active', false)->count(),
            'new_this_month' => Company::where('created_at', '>=', today()->startOfMonth())->count(),
        ];

        return view('livewire.master-data.companies', [
            'companies' => $companies,
            'territories' => $territories,
            'summary' => $summary,
        ]);
    }
}
