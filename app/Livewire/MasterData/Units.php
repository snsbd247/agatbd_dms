<?php

namespace App\Livewire\MasterData;

use App\Models\Unit;
use App\Models\UnitConversion;
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
    public string $kind = 'count';

    // New conversion row: 1 conv_from = conv_factor × conv_to
    public ?int $conv_from = null;
    public ?int $conv_to = null;
    public string $conv_factor = '';

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
        $this->kind = $unit->kind ?? 'count';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('master-data.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'short_code' => 'required|string|max:20|unique:units,short_code,'.($this->editingId ?? 'NULL').',id',
            'kind' => 'required|in:'.implode(',', array_keys(Unit::KINDS)),
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

    public function addConversion(): void
    {
        $this->authorize('master-data.manage');

        $this->validate([
            'conv_from' => 'required|exists:units,id',
            'conv_to' => 'required|exists:units,id|different:conv_from',
            'conv_factor' => 'required|numeric|gt:0',
        ], [], ['conv_from' => __('From'), 'conv_to' => __('To'), 'conv_factor' => __('Factor')]);

        $from = Unit::find($this->conv_from);
        $to = Unit::find($this->conv_to);

        if ($from->kind !== $to->kind) {
            $this->addError('conv_to', __('Only units of the same kind can be converted (e.g. weight to weight).'));

            return;
        }

        $reverse = UnitConversion::where('from_unit_id', $to->id)->where('to_unit_id', $from->id)->exists();

        if ($reverse) {
            $this->addError('conv_to', __('This conversion already exists the other way round.'));

            return;
        }

        UnitConversion::updateOrCreate(
            ['from_unit_id' => $from->id, 'to_unit_id' => $to->id],
            ['factor' => $this->conv_factor],
        );

        $this->reset(['conv_from', 'conv_to', 'conv_factor']);
    }

    public function deleteConversion(int $id): void
    {
        $this->authorize('master-data.manage');
        UnitConversion::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'short_code']);
        $this->kind = 'count';
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
            'conversions' => UnitConversion::with(['fromUnit', 'toUnit'])->get()->sortBy(fn ($c) => $c->fromUnit->name)->values(),
            'allUnitOptions' => Unit::orderBy('kind')->orderBy('name')->get(),
        ]);
    }
}
