<?php

namespace App\Livewire\Production\Orders;

use App\Models\Factory;
use App\Models\Product;
use App\Models\Production\Bom;
use App\Models\Production\Machine;
use App\Models\Production\ProductionOrder;
use App\Services\Production\ProductionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Production Batches')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $status = '';
    #[Url]
    public string $factory = '';

    public bool $showModal = false;
    public ?int $factory_id = null;
    public ?int $product_id = null;
    public ?int $bom_id = null;
    public ?int $machine_id = null;
    public string $planned_quantity = '';
    public string $mfg_date = '';
    public string $batch_no = '';
    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('production.view');
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'factory'], true)) {
            $this->resetPage();
        }
    }

    public function create(?int $productId = null, ?string $quantity = null): void
    {
        $this->authorize('production.manage');
        $this->resetErrorBag();
        $this->reset(['machine_id', 'batch_no', 'notes']);
        $this->factory_id = Factory::where('active', true)->orderBy('name')->value('id');
        $this->product_id = $productId;
        $this->planned_quantity = $quantity ?? '';
        $this->mfg_date = today()->toDateString();
        $this->updatedProductId();
        $this->showModal = true;
    }

    public function updatedProductId(): void
    {
        $this->bom_id = $this->product_id
            ? Bom::where('product_id', $this->product_id)->where('kind', 'production')->where('active', true)->orderByDesc('is_default')->orderByDesc('version')->value('id')
            : null;
        if ($this->bom_id && $this->planned_quantity === '') {
            $this->planned_quantity = (string) (float) Bom::find($this->bom_id)->output_quantity;
        }
    }

    public function save()
    {
        $this->authorize('production.manage');
        $data = $this->validate([
            'factory_id' => 'required|exists:factories,id',
            'product_id' => 'required|exists:products,id',
            'bom_id' => 'required|exists:boms,id',
            'machine_id' => 'nullable|exists:machines,id',
            'planned_quantity' => 'required|numeric|gt:0',
            'mfg_date' => 'required|date',
            'batch_no' => 'nullable|string|max:60',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $order = app(ProductionService::class)->create($data);
        } catch (\RuntimeException $e) {
            $this->addError('product_id', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('production.orders.show', $order, navigate: true);
    }

    public function render()
    {
        $orders = ProductionOrder::with(['product.unit', 'factory', 'machine'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('batch_no', 'like', "%{$this->search}%")
                ->orWhere('number', 'like', "%{$this->search}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->factory, fn ($q) => $q->where('factory_id', $this->factory))
            ->latest('mfg_date')->latest('id')->paginate(25);

        $counts = ProductionOrder::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('livewire.production.orders.index', [
            'orders' => $orders,
            'counts' => $counts,
            'factories' => Factory::orderBy('name')->get(),
            'products' => Product::with('unit')->whereIn('type', ['finished_good', 'semi_finished'])->whereHas('boms', fn ($b) => $b->where('active', true))->orderBy('name')->get(),
            'boms' => $this->product_id ? Bom::where('product_id', $this->product_id)->where('kind', 'production')->where('active', true)->orderByDesc('version')->get() : collect(),
            'machines' => $this->factory_id ? Machine::where('factory_id', $this->factory_id)->where('active', true)->orderBy('code')->get() : collect(),
            'chosen' => $this->product_id ? Product::with('unit')->find($this->product_id) : null,
        ]);
    }
}
