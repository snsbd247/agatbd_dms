<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** A pack of one product: 1 unit = factor × the product's base unit (1 bag = 50 kg). */
class ProductUnit extends Model
{
    use Auditable;

    protected $fillable = ['product_id', 'unit_id', 'factor', 'barcode'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:6'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
