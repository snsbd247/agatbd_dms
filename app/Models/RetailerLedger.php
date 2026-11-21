<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailerLedger extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'retailer_id';

    protected $table = 'retailer_ledger';

    protected $fillable = [
        'retailer_id', 'type', 'reference_type', 'reference_id',
        'debit', 'credit', 'balance_after', 'entry_date',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
