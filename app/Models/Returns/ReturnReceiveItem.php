<?php

namespace App\Models\Returns;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class ReturnReceiveItem extends Model
{
    protected $fillable = ['return_receive_id', 'product_id', 'batch_id', 'quantity', 'disposition'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function returnReceive()
    {
        return $this->belongsTo(ReturnReceive::class);
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
