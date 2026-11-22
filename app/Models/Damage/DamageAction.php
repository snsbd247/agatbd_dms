<?php

namespace App\Models\Damage;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/** What was decided for part (or all) of a damage record. */
class DamageAction extends Model
{
    public const ACTIONS = [
        'repair' => 'Repair',
        'reprocess' => 'Reprocess',
        'discount_sale' => 'Sell at a discount',
        'destroy' => 'Destroy / scrap',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'recovery_price' => 'decimal:4', 'written_off' => 'decimal:2'];
    }

    public function label(): string
    {
        return __(self::ACTIONS[$this->action] ?? $this->action);
    }

    public function record()
    {
        return $this->belongsTo(DamageRecord::class, 'damage_record_id');
    }

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class);
    }

    public function targetWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id')->withoutGlobalScope('tenant');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
