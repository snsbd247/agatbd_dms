<?php

namespace App\Livewire\Damage;

use App\Models\Batch;
use App\Models\Damage\DamageRecord;
use App\Models\Warehouse;
use App\Services\Damage\DamageService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** The damage register: report, approve, and see what is in the damage store. */
#[Layout('layouts.app')]
#[Title('Damage Register')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';
    #[Url]
    public string $source = '';
    #[Url]
    public string $search = '';

    public bool $showModal = false;
    public ?int $warehouse_id = null;
    public ?int $batch_id = null;
    public string $quantity = '';
    public string $report_source = 'warehouse';
    public string $reason = '';
    public string $damage_date = '';
    public string $batchSearch = '';

    public function mount(): void
    {
        $this->authorize('damage.view');
    }

    public function updating($name): void
    {
        if (in_array($name, ['status', 'source', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('damage.manage');
        $this->resetErrorBag();
        $this->reset(['batch_id', 'quantity', 'reason', 'batchSearch']);
        $this->report_source = 'warehouse';
        $this->damage_date = today()->toDateString();
        $this->warehouse_id = Warehouse::where('active', true)->where('purpose', '!=', 'damage')->orderBy('name')->value('id');
        $this->showModal = true;
    }

    public function updatedWarehouseId(): void
    {
        $this->batch_id = null;
    }

    public function save(): void
    {
        $this->authorize('damage.manage');
        $this->validate([
            'batch_id' => 'required|integer',
            'quantity' => 'required|numeric|gt:0',
            'report_source' => 'required|in:warehouse,transport,other',
            'reason' => 'required|string|max:1000',
            'damage_date' => 'required|date|before_or_equal:today',
        ], [], ['batch_id' => __('batch')]);

        try {
            $record = app(DamageService::class)->report($this->batch_id, (float) $this->quantity, $this->report_source, $this->reason, $this->damage_date);
            if (auth()->user()->can('damage.approve')) {
                app(DamageService::class)->approve($record);
            }
        } catch (\RuntimeException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }
        $this->showModal = false;
    }

    public function approve(int $id): void
    {
        $this->authorize('damage.approve');
        try {
            app(DamageService::class)->approve(DamageRecord::findOrFail($id));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reject(int $id): void
    {
        $this->authorize('damage.approve');
        try {
            app(DamageService::class)->reject(DamageRecord::findOrFail($id), null);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $records = DamageRecord::with(['product.unit', 'warehouse', 'foundWarehouse'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->source, fn ($q) => $q->where('source', $this->source))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->search}%")->orWhere('batch_no', 'like', "%{$this->search}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
            ->latest('damage_date')->latest('id')->paginate(25);

        $counts = DamageRecord::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $inStore = DamageRecord::where('status', 'approved')->get();

        $batches = $this->showModal && $this->warehouse_id
            ? Batch::with('product.unit')->where('warehouse_id', $this->warehouse_id)->where('quantity_available', '>', 0)
                ->when($this->batchSearch, fn ($q) => $q->where(fn ($w) => $w->where('batch_no', 'like', "%{$this->batchSearch}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->batchSearch}%"))))
                ->orderBy('product_id')->limit(60)->get()
            : collect();

        return view('livewire.damage.index', [
            'records' => $records,
            'counts' => $counts,
            'openValue' => round($inStore->sum(fn ($r) => (float) $r->quantity_open * (float) $r->unit_cost), 2),
            'warehouses' => Warehouse::where('active', true)->where('purpose', '!=', 'damage')->orderBy('name')->get(),
            'batches' => $batches,
        ]);
    }
}
