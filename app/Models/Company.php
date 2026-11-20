<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'phone', 'email', 'address', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(\App\Models\Purchase\PurchaseOrder::class);
    }

    public function purchaseReturns()
    {
        return $this->hasMany(\App\Models\Purchase\PurchaseReturn::class);
    }

    public function companyReturns()
    {
        return $this->hasMany(\App\Models\Returns\CompanyReturn::class);
    }
}
