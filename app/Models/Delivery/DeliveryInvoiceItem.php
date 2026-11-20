<?php

namespace App\Models\Delivery;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sales\SalesOrderItem;
use Illuminate\Database\Eloquent\Model;

class DeliveryInvoiceItem extends Model
{
    protected $fillable = [
        'delivery_invoice_id', 'sales_order_item_id', 'product_id',
        'batch_id', 'quantity', 'unit_price', 'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function deliveryInvoice()
    {
        return $this->belongsTo(DeliveryInvoice::class);
    }

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class);
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
