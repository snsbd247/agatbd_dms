<?php

namespace App\Livewire\MasterData;

use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Categories')]
class Categories extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public bool $showViewModal = false;
    public ?int $editingId = null;
    public ?int $viewingId = null;

    public ?int $parent_id = null;
    public string $name = '';
    public string $code = '';
    public bool $active = true;

    public string $search = '';
    public string $filter_status = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_status']);
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
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->parent_id = $category->parent_id;
        $this->name = $category->name;
        $this->code = $category->code;
        $this->active = $category->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('master-data.manage');

        $data = $this->validate([
            'parent_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:categories,code,'.($this->editingId ?? 'NULL').',id',
            'active' => 'boolean',
        ]);

        Category::updateOrCreate(['id' => $this->editingId], $data);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('master-data.manage');
        Category::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'parent_id', 'name', 'code']);
        $this->active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $categories = Category::query()
            ->with('parent')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
            ->when($this->filter_status !== '', fn ($q) => $q->where('active', $this->filter_status === 'active'))
            ->orderBy('name')
            ->paginate(10);

        $parents = Category::orderBy('name')->get();

        $summary = [
            'total' => Category::count(),
            'active' => Category::where('active', true)->count(),
            'inactive' => Category::where('active', false)->count(),
            'new_this_month' => Category::where('created_at', '>=', today()->startOfMonth())->count(),
        ];

        return view('livewire.master-data.categories', [
            'categories' => $categories,
            'parents' => $parents,
            'summary' => $summary,
            'viewingCategory' => $this->viewingId ? Category::with('parent')->find($this->viewingId) : null,
        ]);
    }
}
