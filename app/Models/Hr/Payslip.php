<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** One employee's line on a salary sheet. */
class Payslip extends Model
{
    use BelongsToTenant;

    public const TENANT_PATH = 'employee_id';

    public const MONEY = [
        'basic', 'house_rent', 'medical', 'conveyance', 'other_allowance', 'overtime_amount',
        'commission', 'bonus', 'gross', 'absent_deduction', 'advance_deduction', 'loan_deduction', 'other_deduction', 'net',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return array_fill_keys(self::MONEY, 'decimal:2');
    }

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductions(): float
    {
        return round((float) $this->absent_deduction + (float) $this->advance_deduction + (float) $this->loan_deduction + (float) $this->other_deduction, 2);
    }

    /** Recompute gross and net after an edit (bonus, other deduction …). */
    public function recalculate(): void
    {
        $earned = (float) $this->basic + (float) $this->house_rent + (float) $this->medical + (float) $this->conveyance
            + (float) $this->other_allowance + (float) $this->overtime_amount + (float) $this->commission + (float) $this->bonus;
        $this->gross = round($earned, 2);
        $this->net = round($earned - $this->deductions(), 2);
    }
}
