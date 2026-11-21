<?php

namespace App\Models\Purchase;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    use Auditable;

    protected $fillable = ['number', 'purchase_order_id', 'warehouse_id', 'receipt_date', 'total_amount', 'created_by'];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
