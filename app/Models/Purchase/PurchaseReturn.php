<?php

namespace App\Models\Purchase;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'warehouse_id';

    use Auditable;

    protected $fillable = ['number', 'company_id', 'warehouse_id', 'return_date', 'reason', 'status', 'total_amount'];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
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

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
