<?php

namespace App\Models\Returns;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class CompanyReturnItem extends Model
{
    protected $fillable = ['company_return_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function companyReturn()
    {
        return $this->belongsTo(CompanyReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
