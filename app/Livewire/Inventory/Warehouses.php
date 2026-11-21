<?php

namespace App\Livewire\Inventory;

use App\Models\Warehouse;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Warehouses')]
class Warehouses extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public bool $showViewModal = false;
    public ?int $editingId = null;
    public ?int $viewingId = null;

    public string $name = '';
    public string $type = 'branch';
    public string $address = '';
    public ?int $factory_id = null;
    public string $purpose = 'general';
    public bool $active = true;

    public string $search = '';
    public string $filter_status = '';
    public string $filter_type = '';
    public string $filter_purpose = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_status', 'filter_type', 'filter_purpose']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('inventory.manage');
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
        $this->authorize('inventory.manage');
        $warehouse = Warehouse::findOrFail($id);
        $this->editingId = $warehouse->id;
        $this->name = $warehouse->name;
        $this->type = $warehouse->type;
        $this->address = (string) $warehouse->address;
        $this->factory_id = $warehouse->factory_id;
        $this->purpose = $warehouse->purpose ?? 'general';
        $this->active = $warehouse->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('inventory.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:central,branch',
            'factory_id' => 'nullable|exists:factories,id',
            'purpose' => 'required|in:'.implode(',', array_keys(Warehouse::PURPOSES)),
            'address' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);

        Warehouse::updateOrCreate(['id' => $this->editingId], $data);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('inventory.manage');
        Warehouse::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'address', 'factory_id']);
        $this->purpose = 'general';
        $this->type = 'branch';
        $this->active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $warehouses = Warehouse::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filter_status !== '', fn ($q) => $q->where('active', $this->filter_status === 'active'))
            ->when($this->filter_type, fn ($q) => $q->where('type', $this->filter_type))
            ->when($this->filter_purpose, fn ($q) => $q->where('purpose', $this->filter_purpose))
            ->with('factory')
            ->orderBy('name')
            ->paginate(10);

        $summary = [
            'total' => Warehouse::count(),
            'active' => Warehouse::where('active', true)->count(),
            'central' => Warehouse::where('type', 'central')->count(),
            'branch' => Warehouse::where('type', 'branch')->count(),
        ];

        return view('livewire.inventory.warehouses', [
            'warehouses' => $warehouses,
            'summary' => $summary,
            'viewingWarehouse' => $this->viewingId ? Warehouse::with('factory')->find($this->viewingId) : null,
            'factories' => \App\Models\Factory::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
