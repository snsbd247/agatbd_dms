<?php

namespace App\Livewire\Damage;

use App\Models\Damage\DamageRecord;
use App\Models\Warehouse;
use App\Services\Damage\DamageService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** One damage record: what happened, and the decisions for it. */
#[Layout('layouts.app')]
class Show extends Component
{
    public DamageRecord $record;

    public bool $showDecide = false;
    public string $action = 'repair';
    public string $quantity = '';
    public string $recovery_price = '';
    public ?int $target_warehouse_id = null;
    public string $mode = 'in_house';
    public string $vendor = '';
    public string $expected_date = '';
    public string $estimated_cost = '';
    public string $notes = '';

    public function mount(DamageRecord $record): void
    {
        $this->authorize('damage.view');
        $this->record = $record;
    }

    public function openDecide(string $action = 'repair'): void
    {
        $this->authorize('damage.manage');
        $this->resetErrorBag();
        $this->reset(['recovery_price', 'vendor', 'expected_date', 'estimated_cost', 'notes']);
        $this->action = $action;
        $this->mode = 'in_house';
        $this->quantity = (string) (float) $this->record->quantity_open;
        $this->target_warehouse_id = null;
        $this->showDecide = true;
    }

    public function decide(): void
    {
        $this->authorize('damage.manage');
        $this->validate([
            'action' => 'required|in:repair,reprocess,discount_sale,destroy',
            'quantity' => 'required|numeric|gt:0',
            'recovery_price' => 'required_if:action,discount_sale|nullable|numeric|min:0',
            'target_warehouse_id' => 'nullable|integer',
            'mode' => 'required|in:in_house,outsourced',
            'vendor' => 'required_if:mode,outsourced|nullable|string|max:255',
            'expected_date' => 'nullable|date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ], [], ['vendor' => __('workshop')]);

        try {
            app(DamageService::class)->decide($this->record, $this->action, (float) $this->quantity, [
                'target_warehouse_id' => $this->target_warehouse_id,
                'recovery_price' => $this->recovery_price !== '' ? (float) $this->recovery_price : null,
                'mode' => $this->mode,
                'vendor' => $this->vendor ?: null,
                'expected_date' => $this->expected_date ?: null,
                'estimated_cost' => $this->estimated_cost !== '' ? (float) $this->estimated_cost : 0,
                'notes' => $this->notes ?: null,
            ]);
        } catch (\RuntimeException $e) {
            $this->addError('decide', $e->getMessage());

            return;
        }
        $this->showDecide = false;
        $this->record->refresh();
    }

    public function approve(): void
    {
        $this->authorize('damage.approve');
        try {
            app(DamageService::class)->approve($this->record);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
        $this->record->refresh();
    }

    public function render()
    {
        $this->record->load(['product.unit', 'warehouse', 'foundWarehouse', 'batch', 'actions.repairJob', 'actions.targetWarehouse', 'actions.creator', 'reporter', 'approver']);
        $store = $this->record->warehouse;

        return view('livewire.damage.show', [
            'targets' => Warehouse::where('active', true)->whereNotIn('purpose', ['damage', 'raw_material'])
                ->where(fn ($w) => $store?->dealer_id ? $w->where('dealer_id', $store->dealer_id) : $w->whereNull('dealer_id'))
                ->orderBy('name')->get(),
            'entries' => \App\Models\Accounts\JournalEntry::where('source_type', (new \App\Models\Damage\DamageAction())->getMorphClass())
                ->whereIn('source_id', $this->record->actions->pluck('id'))->orderBy('id')->get(),
        ])->title($this->record->number);
    }
}
