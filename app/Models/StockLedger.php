<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLedger extends Model
{
    protected $table = 'stock_ledger';

    protected $fillable = [
        'product_id', 'warehouse_id', 'batch_id', 'type', 'quantity',
        'reference_type', 'reference_id', 'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
