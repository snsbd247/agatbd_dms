<?php

namespace App\Livewire\Portal;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SalesOrderPending;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A retailer places an order for themselves. Prices always come from the
 * product master at save time (never from the browser), the order goes to the
 * main warehouse, and it waits for dealer approval like any other order.
 */
#[Layout('layouts.app')]
#[Title('New Order')]
class OrderCreate extends Component
{
    use ForCurrentRetailer;

    /** @var array<int, array{product_id: int, quantity: float|string}> */
    public array $items = [];
    public string $productSearch = '';
    public ?int $categoryId = null;

    public function mount(): void
    {
        $this->retailer();
    }

    private function warehouseId(): ?int
    {
        return Warehouse::where('active', true)
            ->orderByRaw("CASE WHEN type = 'central' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->value('id');
    }

    public function addProduct(int $productId): void
    {
        $product = Product::whereKey($productId)->where('active', true)->sellable()->firstOrFail();
        $index = collect($this->items)->search(fn ($item) => (int) $item['product_id'] === $product->id);

        if ($index !== false) {
            $this->items[$index]['quantity'] = (float) $this->items[$index]['quantity'] + 1;

            return;
        }

        $this->items[] = ['product_id' => $product->id, 'quantity' => 1];
    }

    public function changeQuantity(int $index, int $delta): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $quantity = (float) $this->items[$index]['quantity'] + $delta;

        if ($quantity <= 0) {
            $this->removeItem($index);

            return;
        }

        $this->items[$index]['quantity'] = $quantity;
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(NumberGeneratorService $numbers): void
    {
        $retailer = $this->retailer();

        $this->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001|max:100000',
        ], [
            'items.required' => 'Add at least one product to your order.',
        ]);

        $warehouseId = $this->warehouseId();

        if (! $warehouseId) {
            $this->addError('items', 'Ordering is not available right now. Please contact the dealer.');

            return;
        }

        $order = DB::transaction(function () use ($numbers, $retailer, $warehouseId) {
            $lines = [];
            $total = 0;

            foreach ($this->items as $item) {
                $product = Product::whereKey($item['product_id'])->where('active', true)->sellable()->firstOrFail();
                $quantity = (float) $item['quantity'];
                $lines[] = ['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => (float) $product->sale_price];
                $total += $quantity * (float) $product->sale_price;
            }

            $status = ((float) $retailer->current_due + $total) > (float) $retailer->credit_limit ? 'credit_hold' : 'pending';

            $order = SalesOrder::create([
                'number' => $numbers->generate('sales_order'),
                'order_type' => 'retailer',
                'retailer_id' => $retailer->id,
                'representative_id' => $retailer->representative_id,
                'warehouse_id' => $warehouseId,
                'status' => $status,
                'order_date' => today(),
                'subtotal' => $total,
                'discount' => 0,
                'grand_total' => $total,
            ]);

            foreach ($lines as $line) {
                SalesOrderItem::create(['sales_order_id' => $order->id, ...$line]);
            }

            return $order;
        });

        $recipients = User::role(['Dealer', 'Manager'])->get()->filter(fn (User $u) => $u->wantsNotification('sales_order_pending'));
        Notification::send($recipients, new SalesOrderPending($order));

        session()->flash('status', "Order {$order->number} was placed and is waiting for the dealer's approval.");
        $this->redirect(route('portal.orders.show', $order), navigate: false);
    }

    public function render()
    {
        $retailer = $this->retailer();
        $warehouseId = $this->warehouseId();

        $products = Product::query()
            ->with('unit')
            ->where('active', true)
            ->sellable()
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->productSearch, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->productSearch}%")
                ->orWhere('sku', 'like', "%{$this->productSearch}%")
                ->orWhere('barcode', 'like', "%{$this->productSearch}%")))
            ->withSum(['stocks as warehouse_stock' => fn ($q) => $q->where('warehouse_id', $warehouseId)], 'quantity')
            ->orderBy('name')
            ->limit(60)
            ->get();

        $cartProducts = Product::with('unit')->whereIn('id', collect($this->items)->pluck('product_id'))->get()->keyBy('id');
        $total = collect($this->items)->sum(fn ($i) => (float) $i['quantity'] * (float) ($cartProducts->get($i['product_id'])?->sale_price ?? 0));
        $available = max(0, (float) $retailer->credit_limit - (float) $retailer->current_due);

        return view('livewire.portal.order-create', [
            'retailer' => $retailer,
            'products' => $products,
            'cartProducts' => $cartProducts,
            'categories' => Category::whereHas('products', fn ($q) => $q->where('active', true))->orderBy('name')->get(),
            'total' => $total,
            'availableCredit' => $available,
        ]);
    }
}
