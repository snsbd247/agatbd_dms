<?php

namespace App\Livewire\Costing;

use App\Models\Costing\CostHead;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The overheads offered on new cost sheets, with their usual rates. */
#[Layout('layouts.app')]
#[Title('Cost Heads')]
class Heads extends Component
{
    public ?int $editingId = null;
    public bool $showModal = false;
    public string $name = '';
    public string $code = '';
    public string $basis = 'percent';
    public string $default_value = '0';
    public string $applies_to = 'all';
    public string $sort = '0';
    public bool $active = true;

    public function mount(): void
    {
        $this->authorize('costing.view');
    }

    public function create(): void
    {
        $this->authorize('costing.manage');
        $this->reset(['editingId', 'name', 'code']);
        $this->basis = 'percent';
        $this->default_value = '0';
        $this->applies_to = 'all';
        $this->sort = (string) ((int) CostHead::max('sort') + 1);
        $this->active = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('costing.manage');
        $h = CostHead::findOrFail($id);
        $this->editingId = $h->id;
        $this->name = $h->name;
        $this->code = $h->code;
        $this->basis = $h->basis;
        $this->default_value = rtrim(rtrim((string) $h->default_value, '0'), '.') ?: '0';
        $this->applies_to = $h->applies_to;
        $this->sort = (string) $h->sort;
        $this->active = $h->active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('costing.manage');
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:20', Rule::unique('cost_heads', 'code')->ignore($this->editingId)],
            'basis' => 'required|in:'.implode(',', array_keys(CostHead::BASES)),
            'default_value' => 'required|numeric|min:0',
            'applies_to' => 'required|in:'.implode(',', array_keys(CostHead::APPLIES)),
            'sort' => 'required|integer|min:0|max:999',
            'active' => 'boolean',
        ]);
        $data['code'] = strtoupper($data['code']);
        CostHead::updateOrCreate(['id' => $this->editingId], $data);
        $this->showModal = false;
    }

    public function render()
    {
        return view('livewire.costing.heads', ['heads' => CostHead::orderBy('sort')->orderBy('id')->get()]);
    }
}
