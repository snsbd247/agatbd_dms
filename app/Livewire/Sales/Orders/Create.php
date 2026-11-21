<?php

namespace App\Livewire\Sales\Orders;

use App\Models\Product;
use App\Models\Category;
use App\Models\Retailer;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SalesOrderPending;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Sales Order')]
class Create extends Component
{
    public ?int $retailer_id = null;
    public ?int $warehouse_id = null;
    public string $order_date;

    public array $items = [];
    public string $productSearch = '';
    public ?int $categoryId = null;

    public function mount(): void
    {
        $this->authorize('sales.create');
        $this->order_date = today()->toDateString();

        // e.g. "New order for this dealer" on the dealer page
        if ($preset = request()->integer('retailer')) {
            if (Retailer::whereKey($preset)->exists()) {
                $this->retailer_id = $preset;
            }
        }
    }

    public function addProduct(int $productId): void
    {
        $this->authorize('sales.create');

        $product = Product::whereKey($productId)->where('active', true)->sellable()->firstOrFail();
        $index = collect($this->items)->search(fn ($item) => (int) $item['product_id'] === $product->id);

        if ($index !== false) {
            $this->items[$index]['quantity'] = (float) $this->items[$index]['quantity'] + 1;

            return;
        }

        $this->items[] = ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => (string) $product->sale_price];
    }

    public function incrementQuantity(int $index): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['quantity'] = (float) $this->items[$index]['quantity'] + 1;
        }
    }

    public function decrementQuantity(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $quantity = (float) $this->items[$index]['quantity'];
        if ($quantity <= 1) {
            $this->removeItem($index);

            return;
        }

        $this->items[$index]['quantity'] = $quantity - 1;
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(NumberGeneratorService $numbers): void
    {
        $this->authorize('sales.create');

        $data = $this->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        // Both must be visible to this user, and the goods must leave the
        // seller's own warehouse (head office or the customer's dealer).
        $retailerRow = Retailer::find($data['retailer_id']);
        $warehouseRow = Warehouse::find($data['warehouse_id']);

        if (! $retailerRow || ! $warehouseRow || (int) $retailerRow->dealer_id !== (int) $warehouseRow->dealer_id) {
            $this->addError('warehouse_id', __('Choose a warehouse of the same business as this customer.'));

            return;
        }

        $representative = Auth::user()->representative;

        $order = DB::transaction(function () use ($data, $numbers, $representative) {
            $retailer = Retailer::findOrFail($data['retailer_id']);

            $total = 0;
            foreach ($data['items'] as $index => $item) {
                $product = Product::whereKey($item['product_id'])->where('active', true)->sellable()->firstOrFail();
                $this->items[$index]['unit_price'] = (string) $product->sale_price;
                $total += $item['quantity'] * (float) $product->sale_price;
            }

            $status = ($retailer->current_due + $total) > $retailer->credit_limit ? 'credit_hold' : 'pending';

            $order = SalesOrder::create([
                'number' => $numbers->generate('sales_order'),
                'order_type' => $representative ? 'representative' : 'retailer',
                'retailer_id' => $retailer->id,
                'representative_id' => $representative?->id,
                'warehouse_id' => $data['warehouse_id'],
                'status' => $status,
                'order_date' => $data['order_date'],
                'subtotal' => $total,
                'discount' => 0,
                'grand_total' => $total,
            ]);

            foreach ($data['items'] as $index => $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $this->items[$index]['unit_price'],
                ]);
            }

            return $order;
        });

        if (in_array($order->status, ['pending', 'credit_hold'], true)) {
            $recipients = \App\Support\Staff::approvers(\App\Support\Staff::dealerOfRetailer($order->retailer_id), 'sales_order_pending');
            Notification::send($recipients, new SalesOrderPending($order));
        }

        $this->redirect(route('sales.orders.show', $order), navigate: false);
    }

    public function render()
    {
        $user = Auth::user();
        $representative = $user->representative;

        $retailers = $representative
            ? Retailer::where('representative_id', $representative->id)->orderBy('name')->get()
            : Retailer::orderBy('name')->get();

        $products = Product::query()
            ->where('active', true)
            ->sellable()
            ->when($this->categoryId, fn ($query) => $query->where('category_id', $this->categoryId))
            ->when($this->productSearch, fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.$this->productSearch.'%')
                ->orWhere('sku', 'like', '%'.$this->productSearch.'%')
                ->orWhere('barcode', 'like', '%'.$this->productSearch.'%')))
            ->withSum(['stocks as warehouse_stock' => fn ($query) => $query->when($this->warehouse_id, fn ($stock) => $stock->where('warehouse_id', $this->warehouse_id))], 'quantity')
            ->orderBy('name')
            ->limit(60)
            ->get();

        return view('livewire.sales.orders.create', [
            'retailers' => $retailers,
            'warehouses' => Warehouse::where('active', true)
                ->when($this->retailer_id && ($chosen = Retailer::find($this->retailer_id)), fn ($q) => $chosen->dealer_id === null
                    ? $q->whereNull('dealer_id')
                    : $q->where('dealer_id', $chosen->dealer_id))
                ->orderBy('name')->get(),
            'categories' => Category::whereHas('products', fn ($query) => $query->where('active', true))->orderBy('name')->get(),
            'products' => $products,
            'cartProducts' => Product::whereIn('id', collect($this->items)->pluck('product_id')->filter())->get()->keyBy('id'),
        ]);
    }
}
