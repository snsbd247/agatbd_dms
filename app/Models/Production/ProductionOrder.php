<?php

namespace App\Models\Production;

use App\Models\Batch;
use App\Models\Concerns\Auditable;
use App\Models\Factory;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/** One production batch. */
class ProductionOrder extends Model
{
    use Auditable;

    public const STATUSES = [
        'planned' => 'Planned',
        'in_progress' => 'In production',
        'qc' => 'Quality check',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mfg_date' => 'date',
            'expiry_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'planned_quantity' => 'decimal:3',
            'good_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
            'standard_cost' => 'decimal:2',
            'material_cost' => 'decimal:2',
            'labour_cost' => 'decimal:2',
            'overhead_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    /** Good output as a share of the planned quantity, in %. */
    public function yieldPercent(): ?float
    {
        return (float) $this->planned_quantity > 0 && $this->status === 'completed'
            ? round((float) $this->good_quantity / (float) $this->planned_quantity * 100, 1)
            : null;
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function rawWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'raw_warehouse_id');
    }

    public function outputWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'output_warehouse_id');
    }

    public function damageWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'damage_warehouse_id');
    }

    public function outputBatch()
    {
        return $this->belongsTo(Batch::class, 'output_batch_id');
    }

    public function issues()
    {
        return $this->hasMany(ProductionIssue::class);
    }

    public function qcChecks()
    {
        return $this->hasMany(ProductionQcCheck::class)->latest('checked_at')->latest('id');   // id breaks same-second ties
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
