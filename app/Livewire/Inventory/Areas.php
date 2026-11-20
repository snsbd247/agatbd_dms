<?php

namespace App\Livewire\Inventory;

use App\Models\Area;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Areas')]
class Areas extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public bool $showViewModal = false;
    public ?int $editingId = null;
    public ?int $viewingId = null;

    public string $name = '';
    public string $code = '';

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
        $area = Area::findOrFail($id);
        $this->editingId = $area->id;
        $this->name = $area->name;
        $this->code = $area->code;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('inventory.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:areas,code,'.($this->editingId ?? 'NULL').',id',
        ]);

        Area::updateOrCreate(['id' => $this->editingId], $data);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('inventory.manage');
        Area::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code']);
        $this->resetErrorBag();
    }

    public function render()
    {
        $areas = Area::query()
            ->withCount(['retailers', 'representatives'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        $summary = [
            'total' => Area::count(),
            'retailers' => \App\Models\Retailer::whereNotNull('area_id')->count(),
            'representatives' => \App\Models\Representative::whereNotNull('area_id')->count(),
            'new_this_month' => Area::where('created_at', '>=', today()->startOfMonth())->count(),
        ];

        return view('livewire.inventory.areas', [
            'areas' => $areas,
            'summary' => $summary,
            'viewingArea' => $this->viewingId ? Area::withCount(['retailers', 'representatives'])->find($this->viewingId) : null,
        ]);
    }
}
