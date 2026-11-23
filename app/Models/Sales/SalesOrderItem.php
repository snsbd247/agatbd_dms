<?php

namespace App\Models\Sales;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $fillable = ['sales_order_id', 'product_id', 'quantity', 'unit_price', 'scheme_id', 'list_price', 'is_free', 'delivered_quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'is_free' => 'boolean',
            'unit_price' => 'decimal:2',
            'delivered_quantity' => 'decimal:3',
        ];
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scheme()
    {
        return $this->belongsTo(\App\Models\Scheme::class);
    }
}