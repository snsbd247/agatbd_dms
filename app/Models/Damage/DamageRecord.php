<?php

namespace App\Models\Damage;

use App\Models\Batch;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/** One entry of the damage register. */
class DamageRecord extends Model
{
    use Auditable;
    use BelongsToTenant;

    public const TENANT_PATH = 'warehouse_id';

    public const SOURCES = [
        'warehouse' => 'Found in warehouse',
        'transport' => 'Damaged in transport',
        'return' => 'Customer return',
        'production' => 'Rejected in production',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'reported' => 'Waiting for approval',
        'approved' => 'In damage store',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'damage_date' => 'date',
            'approved_at' => 'datetime',
            'quantity' => 'decimal:3',
            'quantity_open' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function sourceLabel(): string
    {
        return __(self::SOURCES[$this->source] ?? $this->source);
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function value(): float
    {
        return round((float) $this->quantity * (float) $this->unit_cost, 2);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScope('tenant');
    }

    public function foundWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'found_warehouse_id')->withoutGlobalScope('tenant');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function actions()
    {
        return $this->hasMany(DamageAction::class)->orderBy('id');
    }

    public function repairJobs()
    {
        return $this->hasMany(RepairJob::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
