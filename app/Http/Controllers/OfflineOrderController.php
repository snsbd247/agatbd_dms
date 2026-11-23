<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\Warehouse;
use App\Notifications\SalesOrderPending;
use App\Services\NumberGeneratorService;
use App\Services\SchemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Taking orders without internet (representatives in the field).
 *
 *  - data(): the customers and products the phone keeps, plus a fresh
 *    CSRF token for the next sync.
 *  - sync(): orders made offline, each with the phone's own id
 *    (client_uuid), so sending the same batch twice makes each order once.
 *    Prices, schemes and the credit check are worked out here, exactly as
 *    for an order typed online; the phone's prices are only a guide.
 */
class OfflineOrderController extends Controller
{
    public function data()
    {
        abort_unless(Auth::user()->can('sales.create'), 403);

        return response()->json([
            'token' => csrf_token(),
            'at' => now()->toIso8601String(),
            'customers' => $this->customers()->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'code' => $r->code, 'shop' => $r->shop_name, 'phone' => $r->phone,
                'dealer' => (bool) $r->account_dealer_id, 'due' => (float) $r->current_due,
            ])->values(),
            'products' => Product::where('active', true)->sellable()->orderBy('name')
                ->get(['id', 'name', 'sku', 'barcode', 'sale_price', 'dealer_price'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'barcode' => $p->barcode,
                    'price' => (float) $p->sale_price, 'dealer_price' => (float) $p->dealer_price])->values(),
        ]);
    }

    public function sync(Request $request, NumberGeneratorService $numbers, SchemeService $schemes)
    {
        abort_unless(Auth::user()->can('sales.create'), 403);
        $data = $request->validate([
            'orders' => 'required|array|max:100',
            'orders.*.uuid' => 'required|string|max:40',
            'orders.*.retailer_id' => 'required|integer',
            'orders.*.order_date' => 'required|date|before_or_equal:today',
            'orders.*.items' => 'required|array|min:1|max:200',
            'orders.*.items.*.product_id' => 'required|integer',
            'orders.*.items.*.quantity' => 'required|numeric|min:0.001|max:1000000',
        ]);

        $allowed = $this->customers()->keyBy('id');
        $results = [];

        foreach ($data['orders'] as $o) {
            $existing = SalesOrder::where('client_uuid', $o['uuid'])->first();
            if ($existing) {
                $results[] = ['uuid' => $o['uuid'], 'ok' => true, 'number' => $existing->number, 'again' => true];

                continue;
            }

            $customer = $allowed[$o['retailer_id']] ?? null;
            $warehouseId = $customer ? $this->warehouseFor($customer) : null;
            if (! $customer || ! $warehouseId) {
                $results[] = ['uuid' => $o['uuid'], 'ok' => false, 'error' => __('This customer cannot be ordered for from your login.')];

                continue;
            }

            try {
                $order = DB::transaction(function () use ($o, $customer, $warehouseId, $numbers) {
                    $rep = Auth::user()->representative;
                    $order = SalesOrder::create([
                        'number' => $numbers->generate('sales_order'),
                        'client_uuid' => $o['uuid'],
                        'order_type' => $rep ? 'representative' : 'retailer',
                        'retailer_id' => $customer->id,
                        'representative_id' => $rep?->id,
                        'warehouse_id' => $warehouseId,
                        'status' => 'pending',
                        'order_date' => $o['order_date'],
                        'subtotal' => 0, 'discount' => 0, 'grand_total' => 0,
                    ]);
                    foreach ($o['items'] as $line) {
                        $product = Product::whereKey($line['product_id'])->where('active', true)->sellable()->first();
                        if (! $product) {
                            throw new \RuntimeException(__('A product on this order is no longer sold.'));
                        }
                        SalesOrderItem::create(['sales_order_id' => $order->id, 'product_id' => $product->id,
                            'quantity' => round((float) $line['quantity'], 3), 'unit_price' => $product->priceFor($customer)]);
                    }

                    return $order;
                });
            } catch (\RuntimeException $e) {
                $results[] = ['uuid' => $o['uuid'], 'ok' => false, 'error' => $e->getMessage()];

                continue;
            }

            // Totals, schemes and the credit check, as for any order.
            $schemes->apply($order->fresh());
            $order->refresh();
            $recipients = \App\Support\Staff::approvers(\App\Support\Staff::dealerOfRetailer($order->retailer_id), 'sales_order_pending');
            Notification::send($recipients, new SalesOrderPending($order));

            $results[] = ['uuid' => $o['uuid'], 'ok' => true, 'number' => $order->number, 'total' => (float) $order->grand_total, 'status' => $order->status];
        }

        return response()->json(['results' => $results, 'token' => csrf_token()]);
    }

    /** Customers this login may order for (a representative: their own). */
    private function customers()
    {
        $rep = Auth::user()->representative;

        return Retailer::where('active', true)
            ->when($rep, fn ($q) => $q->where('representative_id', $rep->id))
            ->orderBy('name')->get();
    }

    /** The seller's sales warehouse for this customer (head office or the customer's dealer). */
    private function warehouseFor(Retailer $customer): ?int
    {
        return Warehouse::where('active', true)
            ->when($customer->dealer_id === null, fn ($q) => $q->whereNull('dealer_id'), fn ($q) => $q->where('dealer_id', $customer->dealer_id))
            ->whereNotIn('purpose', ['damage', 'raw_material', 'production'])
            ->orderByRaw("CASE WHEN purpose = 'general' THEN 0 ELSE 1 END")->orderBy('id')
            ->value('id');
    }
}
