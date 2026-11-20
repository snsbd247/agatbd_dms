<?php

namespace App\Models\Returns;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class ReturnRequestItem extends Model
{
    protected $fillable = ['return_request_id', 'product_id', 'quantity', 'unit_price', 'reason'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
        ];
    }

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
