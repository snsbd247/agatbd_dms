<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Representative extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'dealer_id';

    use Auditable;

    protected $fillable = [
        'user_id', 'dealer_id', 'employee_code', 'name', 'phone', 'area_id',
        'salary_base', 'commission_rate', 'target_amount', 'active',
    ];

    /** Employer: null = Agro Tech head office, otherwise the dealer. */
    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    /** Dealers a head office representative is placed with. */
    public function placedWithDealers()
    {
        return $this->belongsToMany(Dealer::class, 'dealer_representative');
    }

    /**
     * Staff a dealer can see: its own, plus head office staff placed with it.
     * Head office users see head office staff only.
     */
    public function scopeTenantOwner(\Illuminate\Database\Eloquent\Builder $query, string $table, array $allowed): void
    {
        $dealerIds = array_values(array_filter($allowed, fn ($id) => $id !== null));

        $query->where(function ($w) use ($table, $allowed, $dealerIds) {
            \App\Support\Tenant::apply($w, "{$table}.dealer_id", $allowed);

            if ($dealerIds !== []) {
                $w->orWhereIn("{$table}.id", fn ($s) => $s->select('representative_id')->from('dealer_representative')->whereIn('dealer_id', $dealerIds));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'salary_base' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'target_amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function retailers()
    {
        return $this->hasMany(Retailer::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(\App\Models\Sales\SalesOrder::class);
    }

    public function targets()
    {
        return $this->hasMany(\App\Models\Payroll\RepresentativeTarget::class);
    }

    public function commissions()
    {
        return $this->hasMany(\App\Models\Payroll\Commission::class);
    }

    public function advances()
    {
        return $this->hasMany(\App\Models\Payroll\Advance::class);
    }

    public function bonuses()
    {
        return $this->hasMany(\App\Models\Payroll\Bonus::class);
    }

    public function payrollEntries()
    {
        return $this->hasMany(\App\Models\Payroll\PayrollEntry::class);
    }
}
