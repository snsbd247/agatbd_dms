<?php

namespace App\Models\Production;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    protected $fillable = ['bom_id', 'product_id', 'quantity', 'wastage_percent', 'sort'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'wastage_percent' => 'decimal:2'];
    }

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
