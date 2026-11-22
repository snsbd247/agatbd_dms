<?php

namespace App\Models\Production;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/** A quantity of one material batch put into a production batch. */
class ProductionIssue extends Model
{
    protected $fillable = ['production_order_id', 'issue_no', 'product_id', 'batch_id', 'quantity', 'unit_cost', 'issued_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:4'];
    }

    public function order()
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function issuer()
    {
        return $this->belongsTo(\App\Models\User::class, 'issued_by');
    }
}
