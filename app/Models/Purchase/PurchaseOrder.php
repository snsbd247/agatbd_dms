<?php

namespace App\Models\Purchase;

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use Auditable;

    protected $fillable = [
        'number', 'company_id', 'warehouse_id', 'status',
        'order_date', 'expected_date', 'total_amount', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
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
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
