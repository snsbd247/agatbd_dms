<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\Scheme;
use Illuminate\Support\Facades\DB;

/**
 * Applies the running trade schemes to a head-office sales order, after its
 * lines are saved (new order, edited order, portal order):
 *
 *  - the best discount scheme for a line lowers its unit price (the price
 *    before is kept in list_price);
 *  - free-goods schemes add zero-price lines (is_free) for every min_qty
 *    bought.
 *
 * Applying again (after an edit) first takes the previous scheme effects
 * off, so it never doubles up. Dealers' own sales get no head-office
 * schemes.
 */
class SchemeService
{
    /** @return array<int, string> descriptions of the schemes applied */
    public function apply(SalesOrder $order): array
    {
        $customer = Retailer::find($order->retailer_id);
        if (! $customer || $customer->dealer_id !== null) {
            return [];
        }

        return DB::transaction(function () use ($order, $customer) {
            $order->items()->where('is_free', true)->delete();

            $schemes = Scheme::runningFor($order->order_date->toDateString(), $customer)
                ->with(['product', 'category', 'freeProduct'])->get();
            $applied = [];
            $free = [];

            foreach ($order->items()->get() as $line) {
                $product = Product::find($line->product_id);
                $price = (float) ($line->list_price ?? $line->unit_price);
                $qty = (float) $line->quantity;

                $discount = $schemes->where('type', 'discount')
                    ->filter(fn ($s) => $s->covers($product) && $qty >= (float) $s->min_qty)
                    ->sortByDesc(fn ($s) => (float) $s->discount_percent)->first();

                $line->update($discount
                    ? ['list_price' => $price, 'unit_price' => round($price * (1 - (float) $discount->discount_percent / 100), 2), 'scheme_id' => $discount->id]
                    : ['list_price' => null, 'unit_price' => $price, 'scheme_id' => null]);
                if ($discount) {
                    $applied[$discount->id] = $discount->name;
                }

                foreach ($schemes->where('type', 'free_goods') as $s) {
                    if (! $s->free_product_id || (float) $s->min_qty <= 0 || ! $s->covers($product)) {
                        continue;
                    }
                    $times = floor(round($qty / (float) $s->min_qty, 6));
                    if ($times >= 1) {
                        $key = $s->id.'-'.$s->free_product_id;
                        $free[$key] = [
                            'scheme' => $s,
                            'qty' => ($free[$key]['qty'] ?? 0) + $times * (float) $s->free_qty,
                        ];
                        $applied[$s->id] = $s->name;
                    }
                }
            }

            foreach ($free as $f) {
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $f['scheme']->free_product_id,
                    'quantity' => round($f['qty'], 3),
                    'unit_price' => 0,
                    'list_price' => (float) $f['scheme']->freeProduct->priceFor($customer),
                    'scheme_id' => $f['scheme']->id,
                    'is_free' => true,
                ]);
            }

            // Totals and the credit check follow the new prices.
            $subtotal = round((float) $order->items()->get()->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price), 2);
            $discount = min((float) $order->discount, $subtotal);
            $updates = ['subtotal' => $subtotal, 'discount' => $discount, 'grand_total' => round($subtotal - $discount, 2)];
            if (in_array($order->status, ['pending', 'credit_hold'], true)) {
                $updates['status'] = ((float) $customer->current_due + $updates['grand_total']) > (float) $customer->credit_limit ? 'credit_hold' : 'pending';
            }
            $order->update($updates);

            return array_values($applied);
        });
    }
}
