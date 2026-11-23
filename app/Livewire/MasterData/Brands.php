<?php

namespace App\Livewire\MasterData;

use App\Models\Brand;
use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Brands')]
class Brands extends Component
{
    use \App\Livewire\Concerns\DeletesSafely;

    use WithPagination;

    public bool $showModal = false;
    public bool $showViewModal = false;
    public ?int $editingId = null;
    public ?int $viewingId = null;

    public ?int $form_company_id = null;
    public string $name = '';
    public string $code = '';
    public bool $active = true;

    public string $search = '';
    public string $filter_status = '';
    public string $filter_company_id = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_status', 'filter_company_id']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('master-data.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function view(int $id): void
    {
        $this->viewingId = $id;
        $this->showViewModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('master-data.manage');
        $brand = Brand::findOrFail($id);
        $this->editingId = $brand->id;
        $this->form_company_id = $brand->company_id;
        $this->name = $brand->name;
        $this->code = $brand->code;
        $this->active = $brand->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('master-data.manage');

        $data = $this->validate([
            'form_company_id' => 'nullable|exists:companies,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:brands,code,'.($this->editingId ?? 'NULL').',id',
            'active' => 'boolean',
        ]);

        Brand::updateOrCreate(['id' => $this->editingId], [
            'company_id' => $data['form_company_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'active' => $data['active'],
        ]);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('master-data.manage');
        $this->deleteSafely(Brand::findOrFail($id));
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'form_company_id', 'name', 'code']);
        $this->active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $brands = Brand::query()
            ->with('company')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
            ->when($this->filter_status !== '', fn ($q) => $q->where('active', $this->filter_status === 'active'))
            ->when($this->filter_company_id, fn ($q) => $q->where('company_id', $this->filter_company_id))
            ->orderBy('name')
            ->paginate(10);

        $companies = Company::orderBy('name')->get();

        $summary = [
            'total' => Brand::count(),
            'active' => Brand::where('active', true)->count(),
            'inactive' => Brand::where('active', false)->count(),
            'new_this_month' => Brand::where('created_at', '>=', today()->startOfMonth())->count(),
        ];

        return view('livewire.master-data.brands', [
            'brands' => $brands,
            'companies' => $companies,
            'summary' => $summary,
            'viewingBrand' => $this->viewingId ? Brand::with('company')->find($this->viewingId) : null,
        ]);
    }
}
