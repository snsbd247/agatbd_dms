<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'warehouse_id', 'product_id', 'batch_id', 'quantity_change',
        'reason', 'adjusted_by', 'adjustment_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'decimal:3',
            'adjustment_date' => 'date',
        ];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
