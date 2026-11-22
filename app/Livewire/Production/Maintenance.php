<?php

namespace App\Livewire\Production;

use App\Models\Accounts\Account;
use App\Models\Production\Machine;
use App\Models\Production\MachineMaintenance;
use App\Services\Accounts\AutoPosting;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Machine service and breakdown log, with what is due next. */
#[Layout('layouts.app')]
#[Title('Machine Maintenance')]
class Maintenance extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $machine_id = null;
    public string $type = 'preventive';
    public string $maintenance_date = '';
    public string $description = '';
    public string $downtime_hours = '0';
    public string $vendor = '';
    public string $cost = '0';
    public ?int $paid_from_id = null;
    public string $next_due_date = '';

    public function mount(): void
    {
        $this->authorize('production.view');
    }

    public function create(?int $machineId = null): void
    {
        $this->authorize('production.manage');
        $this->resetErrorBag();
        $this->reset(['description', 'vendor', 'next_due_date']);
        $this->machine_id = $machineId ?? Machine::where('active', true)->orderBy('code')->value('id');
        $this->type = 'preventive';
        $this->maintenance_date = today()->toDateString();
        $this->downtime_hours = '0';
        $this->cost = '0';
        $this->paid_from_id = Account::money()->where('is_cash', true)->orderBy('code')->value('id');
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('production.manage');
        $data = $this->validate([
            'machine_id' => 'required|exists:machines,id',
            'type' => 'required|in:preventive,breakdown',
            'maintenance_date' => 'required|date|before_or_equal:today',
            'description' => 'required|string|max:2000',
            'downtime_hours' => 'required|numeric|min:0',
            'vendor' => 'nullable|string|max:255',
            'cost' => 'required|numeric|min:0',
            'paid_from_id' => 'required_unless:cost,0|nullable|integer',
            'next_due_date' => 'nullable|date|after:maintenance_date',
        ]);
        $money = $data['paid_from_id'] ? Account::money()->find($data['paid_from_id']) : null;
        if ((float) $data['cost'] > 0 && ! $money) {
            $this->addError('paid_from_id', __('Choose a cash or bank account.'));

            return;
        }

        DB::transaction(function () use ($data, $money) {
            $m = MachineMaintenance::create([
                'machine_id' => $data['machine_id'],
                'type' => $data['type'],
                'maintenance_date' => $data['maintenance_date'],
                'description' => $data['description'],
                'downtime_hours' => $data['downtime_hours'],
                'vendor' => $data['vendor'] ?: null,
                'cost' => $data['cost'],
                'paid_from_account_id' => (float) $data['cost'] > 0 ? $money?->id : null,
                'next_due_date' => $data['next_due_date'] ?: null,
                'created_by' => auth()->id(),
            ]);
            app(AutoPosting::class)->machineMaintenance($m);
        });

        $this->showModal = false;
    }

    public function render()
    {
        $machines = Machine::with(['factory', 'maintenances'])->where('active', true)->orderBy('code')->get();
        // The latest "next due" of each machine.
        $due = $machines->map(function ($m) {
            $next = $m->maintenances->first(fn ($x) => $x->next_due_date)?->next_due_date;

            return ['machine' => $m, 'next' => $next, 'last' => $m->maintenances->first()?->maintenance_date];
        })->filter(fn ($r) => $r['next'])->sortBy('next')->values();

        $yearStart = \App\Support\FiscalYear::current()['start']->toDateString();

        return view('livewire.production.maintenance', [
            'records' => MachineMaintenance::with(['machine.factory', 'creator'])->latest('maintenance_date')->latest('id')->paginate(25),
            'machines' => $machines,
            'due' => $due,
            'moneyAccounts' => Account::money()->orderBy('code')->get(),
            'yearCost' => (float) MachineMaintenance::whereDate('maintenance_date', '>=', $yearStart)->sum('cost'),
            'yearDowntime' => (float) MachineMaintenance::whereDate('maintenance_date', '>=', $yearStart)->sum('downtime_hours'),
        ]);
    }
}
