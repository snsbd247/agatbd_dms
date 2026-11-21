<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Auditable;

    use HasFactory;

    /** What a product is for. Trading goods are bought and resold as they are. */
    public const TYPES = [
        'raw_material' => 'Raw material',
        'packaging' => 'Packaging material',
        'semi_finished' => 'Semi-finished',
        'finished_good' => 'Finished good',
        'trading' => 'Trading good',
    ];

    /** Types that can go on a sales order. */
    public const SELLABLE_TYPES = ['finished_good', 'trading'];

    /** Types that are bought from suppliers. */
    public const PURCHASABLE_TYPES = ['raw_material', 'packaging', 'trading'];

    protected $fillable = [
        'company_id', 'brand_id', 'category_id', 'unit_id', 'type',
        'sku', 'barcode', 'name', 'description', 'image_path',
        'purchase_price', 'sale_price', 'reorder_level', 'shelf_life_days', 'active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'active' => 'boolean',
            'shelf_life_days' => 'integer',
        ];
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type] ?? $this->type);
    }

    public function isSellable(): bool
    {
        return in_array($this->type, self::SELLABLE_TYPES, true);
    }

    public function scopeSellable($query)
    {
        return $query->whereIn('type', self::SELLABLE_TYPES);
    }

    public function scopePurchasable($query)
    {
        return $query->whereIn('type', self::PURCHASABLE_TYPES);
    }

    /** Extra packs of this product (bag, carton …) with their size in the base unit. */
    public function units()
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class)->orderByDesc('effective_from');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function salesInvoiceItems()
    {
        return $this->hasMany(\App\Models\Sales\SalesInvoiceItem::class);
    }
}
