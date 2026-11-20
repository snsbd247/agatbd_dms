<?php

namespace App\Livewire\MasterData;

use App\Models\Unit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Units')]
class Units extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public bool $showViewModal = false;
    public ?int $editingId = null;
    public ?int $viewingId = null;

    public string $name = '';
    public string $short_code = '';

    public string $search = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
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
        $unit = Unit::findOrFail($id);
        $this->editingId = $unit->id;
        $this->name = $unit->name;
        $this->short_code = $unit->short_code;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('master-data.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'short_code' => 'required|string|max:20|unique:units,short_code,'.($this->editingId ?? 'NULL').',id',
        ]);

        Unit::updateOrCreate(['id' => $this->editingId], $data);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('master-data.manage');
        Unit::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'short_code']);
        $this->resetErrorBag();
    }

    public function render()
    {
        $units = Unit::query()
            ->withCount('products')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('short_code', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        $allUnits = Unit::withCount('products')->get();

        $summary = [
            'total' => $allUnits->count(),
            'in_use' => $allUnits->where('products_count', '>', 0)->count(),
            'unused' => $allUnits->where('products_count', 0)->count(),
            'new_this_month' => Unit::where('created_at', '>=', today()->startOfMonth())->count(),
        ];

        return view('livewire.master-data.units', [
            'units' => $units,
            'summary' => $summary,
            'viewingUnit' => $this->viewingId ? Unit::withCount('products')->find($this->viewingId) : null,
        ]);
    }
}
