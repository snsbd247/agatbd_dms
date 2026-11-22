<?php

namespace App\Models\Production;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/** Batches moved from one warehouse to another (e.g. factory → depot). */
class StockTransfer extends Model
{
    use Auditable;
    use BelongsToTenant;

    public const TENANT_PATH = 'from_warehouse_id';

    protected $fillable = ['number', 'from_warehouse_id', 'to_warehouse_id', 'transfer_date', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date'];
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withoutGlobalScope('tenant');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withoutGlobalScope('tenant');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
