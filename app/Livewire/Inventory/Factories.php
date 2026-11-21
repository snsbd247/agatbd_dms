<?php

namespace App\Livewire\Inventory;

use App\Models\Factory;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Production sites. Each factory's stores (raw material, production floor,
 * finished goods, damage) are ordinary warehouses linked to it.
 */
#[Layout('layouts.app')]
#[Title('Factories')]
class Factories extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public ?int $manager_user_id = null;
    public bool $active = true;

    public function mount(): void
    {
        abort_if(auth()->user()->dealer_id, 403);   // factories belong to the head office
    }

    public function create(): void
    {
        $this->authorize('inventory.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('inventory.manage');
        $factory = Factory::findOrFail($id);
        $this->editingId = $factory->id;
        $this->code = $factory->code;
        $this->name = $factory->name;
        $this->address = (string) $factory->address;
        $this->phone = (string) $factory->phone;
        $this->manager_user_id = $factory->manager_user_id;
        $this->active = $factory->active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('inventory.manage');

        $data = $this->validate([
            'code' => 'required|string|max:20|alpha_dash|unique:factories,code,'.($this->editingId ?? 'NULL').',id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'manager_user_id' => 'nullable|exists:users,id',
            'active' => 'boolean',
        ]);

        $data['code'] = strtoupper($data['code']);
        Factory::updateOrCreate(['id' => $this->editingId], $data);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('inventory.manage');
        $factory = Factory::withCount('warehouses')->findOrFail($id);

        if ($factory->warehouses_count > 0) {
            $this->addError('delete', __('This factory still has warehouses. Move or delete them first.'));

            return;
        }

        $factory->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'address', 'phone', 'manager_user_id']);
        $this->active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.inventory.factories', [
            'factories' => Factory::with(['manager', 'warehouses'])->orderBy('name')->get(),
            'managers' => User::role(['Admin', 'Manager'])->whereNull('dealer_id')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
