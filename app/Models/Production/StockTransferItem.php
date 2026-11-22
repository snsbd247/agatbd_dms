<?php

namespace App\Models\Production;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $fillable = ['stock_transfer_id', 'product_id', 'batch_id', 'to_batch_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class)->withoutGlobalScope('tenant');
    }
}
