<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Representative extends Model
{
    protected $fillable = [
        'user_id', 'employee_code', 'name', 'phone', 'area_id',
        'salary_base', 'commission_rate', 'target_amount', 'active',
    ];

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
