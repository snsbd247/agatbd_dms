<?php

namespace App\Models\Returns;

use App\Models\Retailer;
use App\Models\User;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'retailer_id';

    use Auditable;

    protected $fillable = ['number', 'retailer_id', 'requested_by', 'request_date', 'status', 'reason'];

    protected function casts(): array
    {
        return ['request_date' => 'date'];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items()
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function receives()
    {
        return $this->hasMany(ReturnReceive::class);
    }
}
