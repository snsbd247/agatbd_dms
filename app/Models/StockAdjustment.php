<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'warehouse_id';

    use Auditable;

    protected $fillable = [
        'warehouse_id', 'product_id', 'batch_id', 'quantity_change',
        'reason', 'adjusted_by', 'adjustment_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'decimal:3',
            'adjustment_date' => 'date',
        ];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
