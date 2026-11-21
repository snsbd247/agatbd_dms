<?php

namespace App\Models\Delivery;

use App\Models\Retailer;
use App\Models\Sales\SalesOrder;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class DeliveryInvoice extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'retailer_id';

    use Auditable;

    protected $fillable = [
        'number', 'sales_order_id', 'delivery_note_id', 'retailer_id',
        'invoice_date', 'subtotal', 'discount', 'grand_total', 'status',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function items()
    {
        return $this->hasMany(DeliveryInvoiceItem::class);
    }
}
