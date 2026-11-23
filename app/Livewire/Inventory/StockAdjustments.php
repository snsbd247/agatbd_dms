<?php

namespace App\Livewire\Inventory;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock Adjustments')]
class StockAdjustments extends Component
{
    use WithPagination;

    public const REASONS = [
        'Damaged',
        'Expired',
        'Lost / Theft',
        'Stock count correction',
        'Found stock',
        'Sample / Free issue',
        'Other',
    ];

    public bool $showModal = false;

    public ?int $warehouse_id = null;
    public ?int $product_id = null;
    public ?int $batch_id = null;
    public string $direction = 'remove';
    public string $quantity = '';
    public string $reason = 'Damaged';
    public string $note = '';
    public string $adjustment_date = '';
    public string $productSearch = '';

    public string $search = '';
    public string $filter_warehouse = '';
    public string $filter_direction = '';

    public function updating($name): void
    {
        if (in_array($name, ['search', 'filter_warehouse', 'filter_direction'], true)) {
            $this->resetPage();
        }
    }

    public function updatedWarehouseId(): void
    {
        $this->batch_id = null;
    }

    public function updatedProductId(): void
    {
        $this->batch_id = null;
    }

    public function create(): void
    {
        $this->authorize('inventory.manage');
        $this->reset(['product_id', 'batch_id', 'quantity', 'note', 'productSearch']);
        $this->direction = 'remove';
        $this->reason = 'Damaged';
        $this->warehouse_id ??= Warehouse::where('active', true)->orderBy('name')->value('id');
        $this->adjustment_date = today()->toDateString();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(InventoryService $inventory): void
    {
        $this->authorize('inventory.manage');

        $this->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id' => 'required|exists:products,id',
            'batch_id' => 'nullable|exists:batches,id',
            'direction' => 'required|in:add,remove',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|in:'.implode(',', array_map(fn ($r) => str_replace(',', '', $r), self::REASONS)),
            'note' => 'nullable|string|max:200',
            'adjustment_date' => 'required|date|before_or_equal:today',
        ]);

        $change = (float) $this->quantity * ($this->direction === 'add' ? 1 : -1);
        $reason = trim($this->reason.($this->note !== '' ? ': '.$this->note : ''));

        try {
            $inventory->adjustStock(
                warehouseId: $this->warehouse_id,
                productId: $this->product_id,
                batchId: $this->batch_id,
                change: $change,
                reason: $reason,
                userId: Auth::id(),
                date: $this->adjustment_date,
            );
        } catch (\RuntimeException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->showModal = false;
        session()->flash('status', 'Stock adjusted.');
    }

    /** Undo an adjustment: the opposite movement, and its voucher reversed. */
    public function void(int $id, string $reason): void
    {
        $this->authorize('accounts.manage');
        $this->authorize('inventory.manage');

        try {
            app(\App\Services\VoidService::class)->adjustment(StockAdjustment::findOrFail($id), mb_substr(trim($reason), 0, 200));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        session()->flash('success', __('Adjustment voided.'));
    }
    public function render()
    {
        $adjustments = StockAdjustment::query()
            ->with(['warehouse', 'product.unit', 'batch', 'adjustedBy'])
            ->when($this->search, fn ($q) => $q->whereHas('product', fn ($p) => $p
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")))
            ->when($this->filter_warehouse, fn ($q) => $q->where('warehouse_id', $this->filter_warehouse))
            ->when($this->filter_direction === 'add', fn ($q) => $q->where('quantity_change', '>', 0))
            ->when($this->filter_direction === 'remove', fn ($q) => $q->where('quantity_change', '<', 0))
            ->latest('adjustment_date')
            ->latest('id')
            ->paginate(15);

        $summary = [
            'total' => StockAdjustment::count(),
            'this_month' => StockAdjustment::where('adjustment_date', '>=', today()->startOfMonth())->count(),
            'added' => (float) StockAdjustment::where('quantity_change', '>', 0)->sum('quantity_change'),
            'removed' => abs((float) StockAdjustment::where('quantity_change', '<', 0)->sum('quantity_change')),
        ];

        $formProducts = collect();
        $batches = collect();
        $currentStock = null;
        $selectedProduct = null;

        if ($this->showModal) {
            $formProducts = Product::query()
                ->where('active', true)
                ->when($this->productSearch, fn ($q) => $q->where(fn ($s) => $s
                    ->where('name', 'like', "%{$this->productSearch}%")
                    ->orWhere('sku', 'like', "%{$this->productSearch}%")
                    ->orWhere('barcode', 'like', "%{$this->productSearch}%")))
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'sku']);

            if ($this->product_id) {
                $selectedProduct = Product::with('unit')->find($this->product_id);

                if ($selectedProduct && ! $formProducts->contains('id', $selectedProduct->id)) {
                    $formProducts->prepend($selectedProduct);
                }
            }

            if ($this->product_id && $this->warehouse_id) {
                $currentStock = (float) (Stock::where('warehouse_id', $this->warehouse_id)
                    ->where('product_id', $this->product_id)
                    ->value('quantity') ?? 0);

                $batches = Batch::where('warehouse_id', $this->warehouse_id)
                    ->where('product_id', $this->product_id)
                    ->orderByDesc('quantity_available')
                    ->oldest('created_at')
                    ->get();
            }
        }

        return view('livewire.inventory.stock-adjustments', [
            'adjustments' => $adjustments,
            'voidedIds' => StockAdjustment::where('reason', 'like', 'VOID #%')->pluck('reason')
                ->map(fn ($r) => (int) preg_replace('/^VOID #(\d+):.*$/s', '$1', $r))->all(),
            'summary' => $summary,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'formProducts' => $formProducts,
            'batches' => $batches,
            'currentStock' => $currentStock,
            'selectedProduct' => $selectedProduct,
            'reasons' => self::REASONS,
        ]);
    }
}
