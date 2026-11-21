<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PaymentCollection extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'retailer_id';

    use Auditable;

    protected $table = 'collections';

    protected $fillable = ['number', 'retailer_id', 'collected_by', 'collection_date', 'amount', 'method', 'reference_no'];

    protected function casts(): array
    {
        return [
            'collection_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
