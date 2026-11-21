<?php

namespace App\Models\Returns;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CompanyReturn extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'warehouse_id';

    use Auditable;

    protected $fillable = ['number', 'company_id', 'warehouse_id', 'return_date', 'status'];

    protected function casts(): array
    {
        return ['return_date' => 'date'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(CompanyReturnItem::class);
    }
}
