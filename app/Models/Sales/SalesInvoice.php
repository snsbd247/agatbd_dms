<?php

namespace App\Models\Sales;

use App\Models\Retailer;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class SalesInvoice extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'retailer_id';

    use Auditable;

    protected $fillable = [
        'number', 'sales_order_id', 'retailer_id', 'invoice_date',
        'subtotal', 'discount', 'grand_total', 'vat_amount', 'paid_amount', 'due_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
        ];
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }
}
