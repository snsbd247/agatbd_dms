<?php

namespace App\Models\Delivery;

use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class DeliveryNote extends Model
{
    use Auditable;

    protected $fillable = ['number', 'sales_order_id', 'delivery_type', 'delivered_by', 'delivery_date', 'status'];

    protected function casts(): array
    {
        return ['delivery_date' => 'date'];
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function items()
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(DeliveryInvoice::class);
    }
}
