<?php

namespace App\Livewire\Damage;

use App\Models\Accounts\Account;
use App\Models\Damage\RepairJob;
use App\Models\Warehouse;
use App\Services\Damage\DamageService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Repair and reprocessing jobs: what is out for repair, and finishing them. */
#[Layout('layouts.app')]
#[Title('Repair Jobs')]
class Repairs extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'open';
    #[Url]
    public ?int $job = null;

    public bool $showComplete = false;
    public ?int $completingId = null;
    public string $repaired = '';
    public string $cost = '0';
    public ?int $paid_from_id = null;
    public ?int $return_warehouse_id = null;

    public function mount(): void
    {
        $this->authorize('damage.view');
        if ($this->job) {
            $this->status = '';
        }
    }

    public function updatingStatus(): void
    {
        $this->job = null;
        $this->resetPage();
    }

    public function openComplete(int $id): void
    {
        $this->authorize('damage.manage');
        $job = RepairJob::findOrFail($id);
        $this->resetErrorBag();
        $this->completingId = $job->id;
        $this->repaired = (string) (float) $job->quantity;
        $this->cost = (string) (float) $job->estimated_cost;
        $this->paid_from_id = Account::money()->where('is_cash', true)->orderBy('code')->value('id');
        $this->return_warehouse_id = $job->return_warehouse_id;
        $this->showComplete = true;
    }

    public function complete(): void
    {
        $this->authorize('damage.manage');
        $this->validate([
            'repaired' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'paid_from_id' => 'nullable|integer',
            'return_warehouse_id' => 'nullable|integer',
        ]);
        $money = $this->paid_from_id ? Account::money()->find($this->paid_from_id) : null;

        try {
            app(DamageService::class)->completeRepair(RepairJob::findOrFail($this->completingId), (float) $this->repaired, (float) $this->cost, $money?->id, $this->return_warehouse_id);
        } catch (\RuntimeException $e) {
            $this->addError('complete', $e->getMessage());

            return;
        }
        $this->showComplete = false;
    }

    public function cancelJob(int $id): void
    {
        $this->authorize('damage.manage');
        try {
            app(DamageService::class)->cancelRepair(RepairJob::findOrFail($id));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $jobs = RepairJob::with(['product.unit', 'record', 'warehouse', 'returnWarehouse'])
            ->when($this->job, fn ($q) => $q->whereKey($this->job))
            ->when(! $this->job && $this->status, fn ($q) => $q->where('status', $this->status))
            ->latest('sent_date')->latest('id')->paginate(25);

        $completing = $this->completingId ? RepairJob::with(['product.unit', 'warehouse'])->find($this->completingId) : null;
        $store = $completing?->warehouse;

        return view('livewire.damage.repairs', [
            'jobs' => $jobs,
            'openCount' => RepairJob::where('status', 'open')->count(),
            'overdue' => RepairJob::where('status', 'open')->whereNotNull('expected_date')->whereDate('expected_date', '<', today())->count(),
            'completing' => $completing,
            'moneyAccounts' => Account::money()->orderBy('code')->get(),
            'targets' => $store ? Warehouse::where('active', true)->whereNotIn('purpose', ['damage', 'raw_material'])
                ->where(fn ($w) => $store->dealer_id ? $w->where('dealer_id', $store->dealer_id) : $w->whereNull('dealer_id'))->orderBy('name')->get() : collect(),
        ]);
    }
}
