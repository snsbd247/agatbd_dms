<?php

namespace App\Models\Costing;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/** One line of a cost sheet: a material (per unit, with wastage) or an overhead. */
class CostSheetItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'wastage_percent' => 'decimal:2', 'unit_cost' => 'decimal:4', 'rate' => 'decimal:4', 'amount' => 'decimal:4'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function costHead()
    {
        return $this->belongsTo(CostHead::class);
    }
}
