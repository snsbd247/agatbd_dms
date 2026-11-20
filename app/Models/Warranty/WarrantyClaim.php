<?php

namespace App\Models\Warranty;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use Illuminate\Database\Eloquent\Model;

class WarrantyClaim extends Model
{
    protected $fillable = ['number', 'retailer_id', 'product_id', 'sales_invoice_id', 'claim_date', 'issue_description', 'status'];

    protected function casts(): array
    {
        return ['claim_date' => 'date'];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function tracking()
    {
        return $this->hasMany(WarrantyTracking::class);
    }
}
