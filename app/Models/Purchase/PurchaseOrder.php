<?php

namespace App\Models\Purchase;

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'warehouse_id';

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

    /** Draft or sent with nothing received: the order can be changed or deleted. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true)
            && ! $this->items()->where('received_quantity', '>', 0)->exists()
            && ! $this->goodsReceipts()->exists();
    }

    public function deleteIfEditable(): bool
    {
        if (! $this->isEditable()) {
            return false;
        }
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->items()->delete();
            $this->delete();
        });

        return true;
    }
}