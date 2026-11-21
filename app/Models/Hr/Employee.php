<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Factory;
use App\Models\Representative;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Anyone on the payroll: office staff, factory workers and marketing staff.
 * A marketing employee is linked to their Representative profile (sales,
 * targets, commission).
 */
class Employee extends Model
{
    use Auditable;
    use BelongsToTenant;

    public const TENANT_PATH = 'dealer_id';

    public const CATEGORIES = [
        'office' => 'Office',
        'factory' => 'Factory',
        'marketing' => 'Marketing',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'resigned' => 'Resigned',
        'terminated' => 'Terminated',
    ];

    protected $fillable = [
        'dealer_id', 'employee_code', 'name', 'father_name', 'phone', 'email', 'nid', 'address',
        'date_of_birth', 'gender', 'join_date', 'exit_date', 'status', 'category',
        'department_id', 'designation_id', 'factory_id', 'shift_id', 'user_id', 'representative_id',
        'device_user_id', 'track_attendance', 'overtime_eligible', 'payment_method', 'bank_name', 'bank_account_no',
        'basic', 'house_rent', 'medical', 'conveyance', 'other_allowance',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'join_date' => 'date',
            'exit_date' => 'date',
            'track_attendance' => 'boolean',
            'overtime_eligible' => 'boolean',
            'basic' => 'decimal:2',
            'house_rent' => 'decimal:2',
            'medical' => 'decimal:2',
            'conveyance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
        ];
    }

    public function gross(): float
    {
        return round((float) $this->basic + (float) $this->house_rent + (float) $this->medical + (float) $this->conveyance + (float) $this->other_allowance, 2);
    }

    /** Bangladesh Labour Act s.108: overtime is paid at twice the basic hourly rate (basic ÷ 208). */
    public function overtimeHourlyRate(): float
    {
        return round((float) $this->basic / 208 * 2, 4);
    }

    /** Deduction per day of absence or unpaid leave: basic ÷ 30. */
    public function dailyBasic(): float
    {
        return round((float) $this->basic / 30, 4);
    }

    public function categoryLabel(): string
    {
        return __(self::CATEGORIES[$this->category] ?? $this->category);
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves()
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function loans()
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function settlement()
    {
        return $this->hasOne(FinalSettlement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
