<?php

namespace App\Models\Warranty;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class WarrantyPolicy extends Model
{
    protected $fillable = ['product_id', 'category_id', 'duration_months', 'terms'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
