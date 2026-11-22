<?php

namespace App\Models\Damage;

use App\Models\Accounts\Account;
use App\Models\Batch;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/** Repairing or reprocessing damaged goods, in-house or at an outside workshop. */
class RepairJob extends Model
{
    use Auditable;
    use BelongsToTenant;

    public const TENANT_PATH = 'warehouse_id';

    public const STATUSES = ['open' => 'In repair', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_date' => 'date',
            'expected_date' => 'date',
            'completed_date' => 'date',
            'quantity' => 'decimal:3',
            'repaired_quantity' => 'decimal:3',
            'scrapped_quantity' => 'decimal:3',
            'estimated_cost' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function kindLabel(): string
    {
        return $this->kind === 'reprocess' ? __('Reprocess') : __('Repair');
    }

    public function modeLabel(): string
    {
        return $this->mode === 'outsourced' ? __('Outside workshop') : __('In-house');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->expected_date && $this->expected_date->lt(today());
    }

    public function record()
    {
        return $this->belongsTo(DamageRecord::class, 'damage_record_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScope('tenant');
    }

    public function returnWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'return_warehouse_id')->withoutGlobalScope('tenant');
    }

    public function paidFrom()
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
