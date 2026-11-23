<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A trade scheme on head-office sales:
 *  - discount:   discount_percent off the product (or any product in the
 *                category) when at least min_qty is ordered;
 *  - free_goods: free_qty of free_product_id for every min_qty bought.
 */
class Scheme extends Model
{
    use Auditable;

    public const TYPES = ['discount' => 'Discount', 'free_goods' => 'Free goods'];

    public const AUDIENCES = ['all' => 'All customers', 'dealers' => 'Dealers only', 'retailers' => 'Retailers only'];

    protected $fillable = [
        'name', 'type', 'audience', 'product_id', 'category_id', 'min_qty', 'discount_percent',
        'free_product_id', 'free_qty', 'starts_on', 'ends_on', 'active',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:3',
            'discount_percent' => 'decimal:2',
            'free_qty' => 'decimal:3',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'active' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderLines()
    {
        return $this->hasMany(\App\Models\Sales\SalesOrderItem::class);
    }

    public function freeProduct()
    {
        return $this->belongsTo(Product::class, 'free_product_id');
    }

    /** Running on this date for this customer. */
    public function scopeRunningFor($query, string $date, ?Retailer $customer)
    {
        $isDealer = (bool) $customer?->account_dealer_id;

        return $query->where('active', true)
            ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)
            ->where(fn ($q) => $q->where('audience', 'all')->orWhere('audience', $isDealer ? 'dealers' : 'retailers'));
    }

    public function covers(Product $product): bool
    {
        return ($this->product_id && $this->product_id === $product->id)
            || (! $this->product_id && $this->category_id && $this->category_id === $product->category_id)
            || (! $this->product_id && ! $this->category_id);
    }

    public function describe(): string
    {
        $what = $this->product?->name ?? $this->category?->name ?? __('any product');
        $min = rtrim(rtrim(number_format((float) $this->min_qty, 3), '0'), '.');

        return $this->type === 'discount'
            ? __(':p% off :what (min :min)', ['p' => (float) $this->discount_percent, 'what' => $what, 'min' => $min ?: '0'])
            : __('Buy :min :what, get :free :free_what free', ['min' => $min, 'what' => $what,
                'free' => rtrim(rtrim(number_format((float) $this->free_qty, 3), '0'), '.'), 'free_what' => $this->freeProduct?->name ?? '']);
    }
}
