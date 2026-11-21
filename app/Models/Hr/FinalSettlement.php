<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class FinalSettlement extends Model
{
    use Auditable;
    use \App\Models\Concerns\BelongsToTenant;

    public const TENANT_PATH = 'employee_id';


    protected $fillable = ['number', 'employee_id', 'exit_date', 'reason', 'salary_days', 'salary_amount', 'leave_days', 'leave_encashment', 'service_years', 'gratuity', 'other_payable', 'loan_deduction', 'other_deduction', 'net', 'status', 'paid_from', 'paid_at', 'note'];

    protected function casts(): array
    {
        return [
            'exit_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
