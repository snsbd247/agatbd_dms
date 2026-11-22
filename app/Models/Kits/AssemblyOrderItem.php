<?php

namespace App\Models\Kits;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class AssemblyOrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:4'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
