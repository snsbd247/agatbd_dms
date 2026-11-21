<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'warehouse_id';

    protected $fillable = [
        'product_id', 'warehouse_id', 'batch_no', 'expiry_date',
        'cost_price', 'quantity_received', 'quantity_available',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'cost_price' => 'decimal:2',
            'quantity_received' => 'decimal:3',
            'quantity_available' => 'decimal:3',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(StockLedger::class);
    }
}
