<?php

namespace App\Models\Sales;

use App\Models\Representative;
use App\Models\Retailer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'number', 'order_type', 'retailer_id', 'representative_id', 'warehouse_id',
        'status', 'order_date', 'subtotal', 'discount', 'grand_total', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function deliveryNotes()
    {
        return $this->hasMany(\App\Models\Delivery\DeliveryNote::class);
    }

    public function deliveryInvoices()
    {
        return $this->hasMany(\App\Models\Delivery\DeliveryInvoice::class);
    }
}
