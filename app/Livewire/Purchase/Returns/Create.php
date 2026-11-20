<?php

namespace App\Livewire\Purchase\Returns;

use App\Models\Batch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Purchase\PurchaseReturnItem;
use App\Models\Warehouse;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Purchase Return')]
class Create extends Component
{
    public ?int $company_id = null;
    public ?int $warehouse_id = null;
    public string $return_date;
    public string $reason = '';

    public array $items = [
        ['product_id' => '', 'batch_id' => '', 'quantity' => '', 'unit_cost' => '0.00', 'available' => '0.000'],
    ];

    public function mount(): void
    {
        $this->authorize('purchase.manage');
        $this->return_date = today()->toDateString();
    }

    public function updatedItems($value, $key): void
    {
        if (str($key)->endsWith('.product_id') || str($key)->endsWith('.batch_id')) {
            $index = (int) explode('.', $key)[0];

            if (str($key)->endsWith('.product_id')) {
                $this->items[$index]['batch_id'] = '';
                $this->items[$index]['unit_cost'] = '0.00';
                $this->items[$index]['available'] = '0.000';
            }

            if (str($key)->endsWith('.batch_id') && $value) {
                $batch = Batch::find($value);
                $this->items[$index]['unit_cost'] = $batch ? (string) $batch->cost_price : '0.00';
                $this->items[$index]['available'] = $batch ? (string) $batch->quantity_available : '0.000';
            }
        }
    }

    public function updatedWarehouseId(): void
    {
        foreach ($this->items as $index => $item) {
            $this->items[$index]['batch_id'] = '';
            $this->items[$index]['unit_cost'] = '0.00';
            $this->items[$index]['available'] = '0.000';
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'batch_id' => '', 'quantity' => '', 'unit_cost' => '0.00', 'available' => '0.000'];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(NumberGeneratorService $numbers): void
    {
        $this->authorize('purchase.manage');

        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'return_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.batch_id' => 'required|exists:batches,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        foreach ($data['items'] as $index => $item) {
            $batch = Batch::find($item['batch_id']);

            if (! $batch || bccomp((string) $batch->quantity_available, (string) $item['quantity'], 3) < 0) {
                $this->addError("items.$index.quantity", 'Quantity exceeds the available stock for this batch.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $purchaseReturn = DB::transaction(function () use ($data, $numbers) {
            $total = collect($data['items'])->sum(fn ($item) => $item['quantity'] * (float) $this->itemUnitCost($item));

            $purchaseReturn = PurchaseReturn::create([
                'number' => $numbers->generate('purchase_return'),
                'company_id' => $data['company_id'],
                'warehouse_id' => $data['warehouse_id'],
                'return_date' => $data['return_date'],
                'reason' => $data['reason'] ?: null,
                'status' => 'draft',
                'total_amount' => $total,
            ]);

            foreach ($data['items'] as $item) {
                $batch = Batch::find($item['batch_id']);

                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id' => $item['product_id'],
                    'batch_id' => $item['batch_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $batch->cost_price,
                ]);
            }

            return $purchaseReturn;
        });

        $this->redirect(route('purchase.returns.show', $purchaseReturn), navigate: false);
    }

    private function itemUnitCost(array $item): string
    {
        $batch = Batch::find($item['batch_id']);

        return $batch ? (string) $batch->cost_price : '0';
    }

    public function render()
    {
        $itemBatches = collect($this->items)->mapWithKeys(function ($item, $index) {
            if (! $item['product_id'] || ! $this->warehouse_id) {
                return [$index => collect()];
            }

            return [$index => Batch::where('product_id', $item['product_id'])
                ->where('warehouse_id', $this->warehouse_id)
                ->where('quantity_available', '>', 0)
                ->orderBy('created_at')
                ->get()];
        });

        return view('livewire.purchase.returns.create', [
            'companies' => Company::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'itemBatches' => $itemBatches,
        ]);
    }
}
