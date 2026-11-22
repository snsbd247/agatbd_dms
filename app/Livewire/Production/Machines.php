<?php

namespace App\Livewire\Production;

use App\Models\Factory;
use App\Models\Production\Machine;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Machines & Lines')]
class Machines extends Component
{
    public ?int $editingId = null;
    public bool $showModal = false;
    public ?int $factory_id = null;
    public string $code = '';
    public string $name = '';
    public string $kind = 'machine';
    public string $capacity_per_hour = '';
    public bool $active = true;

    public function mount(): void
    {
        $this->authorize('production.view');
    }

    public function create(): void
    {
        $this->authorize('production.manage');
        $this->reset(['editingId', 'code', 'name', 'capacity_per_hour']);
        $this->kind = 'machine';
        $this->active = true;
        $this->factory_id = Factory::orderBy('name')->value('id');
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('production.manage');
        $m = Machine::findOrFail($id);
        $this->editingId = $m->id;
        $this->fill($m->only(['factory_id', 'code', 'name', 'kind', 'active']));
        $this->capacity_per_hour = (string) ($m->capacity_per_hour ?? '');
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('production.manage');
        $data = $this->validate([
            'factory_id' => 'required|exists:factories,id',
            'code' => ['required', 'string', 'max:20', Rule::unique('machines', 'code')->ignore($this->editingId)],
            'name' => 'required|string|max:255',
            'kind' => 'required|in:line,machine',
            'capacity_per_hour' => 'nullable|numeric|min:0',
            'active' => 'boolean',
        ]);
        $data['capacity_per_hour'] = $data['capacity_per_hour'] !== '' ? $data['capacity_per_hour'] : null;
        $data['code'] = strtoupper($data['code']);

        Machine::updateOrCreate(['id' => $this->editingId], $data);
        $this->showModal = false;
    }

    public function render()
    {
        $machines = Machine::with('factory')->withCount('orders')->orderBy('factory_id')->orderBy('code')->get();

        // Hours of completed production per machine this month.
        $hours = \App\Models\Production\ProductionOrder::whereNotNull('machine_id')->whereNotNull('started_at')->whereNotNull('finished_at')
            ->where('finished_at', '>=', today()->startOfMonth())->get()
            ->groupBy('machine_id')->map(fn ($g) => round($g->sum(fn ($o) => $o->started_at->diffInMinutes($o->finished_at)) / 60, 1));

        return view('livewire.production.machines', [
            'machines' => $machines,
            'factories' => Factory::orderBy('name')->get(),
            'hours' => $hours,
        ]);
    }
}
