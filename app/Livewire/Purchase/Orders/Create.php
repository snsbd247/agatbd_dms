<?php

namespace App\Livewire\Purchase\Orders;

use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderItem;
use App\Models\Warehouse;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Purchase Order')]
class Create extends Component
{
    public ?int $company_id = null;
    public ?int $warehouse_id = null;
    public string $order_date;
    public string $expected_date = '';

    public array $items = [
        ['product_id' => '', 'quantity' => 1, 'unit_price' => 0],
    ];

    /** Set when editing a draft or sent order with nothing received. */
    public ?int $editingId = null;

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        $this->authorize('purchase.manage');
        $this->order_date = today()->toDateString();

        if ($purchaseOrder?->exists) {
            abort_unless($purchaseOrder->isEditable(), 403, __('Only a draft or sent order with nothing received can be changed.'));
            $this->editingId = $purchaseOrder->id;
            $this->company_id = $purchaseOrder->company_id;
            $this->warehouse_id = $purchaseOrder->warehouse_id;
            $this->order_date = $purchaseOrder->order_date->toDateString();
            $this->expected_date = $purchaseOrder->expected_date?->toDateString() ?? '';
            $this->items = $purchaseOrder->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
            ])->all();
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1, 'unit_price' => 0];
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
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', \Illuminate\Validation\Rule::exists('products', 'id')->whereIn('type', Product::PURCHASABLE_TYPES)],
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $editing = $this->editingId ? PurchaseOrder::findOrFail($this->editingId) : null;
        if ($editing && ! $editing->isEditable()) {
            $this->addError('items', __('This order can no longer be changed.'));

            return;
        }

        $order = DB::transaction(function () use ($data, $numbers, $editing) {
            $total = collect($data['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_price']);

            if ($editing) {
                $editing->update([
                    'company_id' => $data['company_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'order_date' => $data['order_date'],
                    'expected_date' => $data['expected_date'] ?: null,
                    'total_amount' => $total,
                ]);
                $editing->items()->delete();
                foreach ($data['items'] as $item) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $editing->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                    ]);
                }

                return $editing;
            }

            $order = PurchaseOrder::create([
                'number' => $numbers->generate('purchase_order'),
                'company_id' => $data['company_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => 'draft',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?: null,
                'total_amount' => $total,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            return $order;
        });

        $this->redirect(route('purchase.orders.show', $order), navigate: false);
    }

    public function render()
    {
        return view('livewire.purchase.orders.create', [
            'companies' => Company::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'products' => Product::purchasable()->orderBy('name')->get(),
        ])->title($this->editingId ? 'Edit Purchase Order' : 'New Purchase Order');
    }
}
