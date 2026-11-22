<?php

namespace App\Livewire\Production;

use App\Models\Batch;
use App\Models\Production\StockTransfer;
use App\Models\Warehouse;
use App\Services\Production\StockTransferService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Moving batches between one's own warehouses (factory → depot, depot →
 * depot). Dealers use it for their own warehouses too.
 */
#[Layout('layouts.app')]
#[Title('Stock Transfers')]
class Transfers extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $from_id = null;
    public ?int $to_id = null;
    public string $transfer_date = '';
    public string $notes = '';
    public string $batchSearch = '';

    /** @var array<int, string> batch id => quantity */
    public array $qty = [];

    public ?int $viewing = null;

    public function mount(): void
    {
        $this->authorize('inventory.view');
    }

    public function create(): void
    {
        $this->authorize('inventory.manage');
        $this->resetErrorBag();
        $this->reset(['to_id', 'notes', 'qty', 'batchSearch']);
        $this->from_id = Warehouse::where('active', true)->orderBy('name')->value('id');
        $this->transfer_date = today()->toDateString();
        $this->showModal = true;
    }

    public function updatedFromId(): void
    {
        $this->qty = [];
    }

    public function save(): void
    {
        $this->authorize('inventory.manage');
        $this->validate([
            'from_id' => 'required|integer',
            'to_id' => 'required|integer|different:from_id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'qty.*' => 'nullable|numeric|min:0',
        ], ['to_id.different' => __('Choose two different warehouses.')]);

        $lines = collect($this->qty)->map(fn ($q, $id) => ['batch_id' => (int) $id, 'quantity' => (float) $q])->values()->all();

        try {
            app(StockTransferService::class)->create($this->from_id, $this->to_id, $this->transfer_date, $lines, $this->notes ?: null);
        } catch (\RuntimeException $e) {
            $this->addError('qty', $e->getMessage());

            return;
        }
        $this->showModal = false;
    }

    public function render()
    {
        $batches = $this->showModal && $this->from_id
            ? Batch::with('product.unit')->where('warehouse_id', $this->from_id)->where('quantity_available', '>', 0)
                ->when($this->batchSearch, fn ($q) => $q->where(fn ($w) => $w->where('batch_no', 'like', "%{$this->batchSearch}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->batchSearch}%"))))
                ->orderBy('product_id')->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')->orderBy('expiry_date')->limit(100)->get()
            : collect();

        return view('livewire.production.transfers', [
            'transfers' => StockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator'])->withCount('items')->latest('transfer_date')->latest('id')->paginate(20),
            'warehouses' => Warehouse::where('active', true)->orderBy('name')->get(),
            'batches' => $batches,
            'detail' => $this->viewing ? StockTransfer::with(['items.product.unit', 'items.batch', 'fromWarehouse', 'toWarehouse'])->find($this->viewing) : null,
        ]);
    }
}
