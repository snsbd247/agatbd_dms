<?php

namespace App\Models\Delivery;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sales\SalesOrderItem;
use Illuminate\Database\Eloquent\Model;

class DeliveryNoteItem extends Model
{
    protected $fillable = ['delivery_note_id', 'sales_order_item_id', 'product_id', 'batch_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class);
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
