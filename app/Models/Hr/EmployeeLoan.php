<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class EmployeeLoan extends Model
{
    use Auditable;
    use \App\Models\Concerns\BelongsToTenant;

    public const TENANT_PATH = 'employee_id';


    protected $fillable = ['employee_id', 'type', 'amount', 'issue_date', 'installment', 'recovered', 'status', 'paid_from', 'note'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'amount' => 'decimal:2',
            'installment' => 'decimal:2',
            'recovered' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function remaining(): float
    {
        return round((float) $this->amount - (float) $this->recovered, 2);
    }

    /** What the next salary takes back: an advance in full, a loan by its installment. */
    public function nextDeduction(): float
    {
        $left = $this->remaining();

        return $this->type === 'advance' || (float) $this->installment <= 0 ? $left : min($left, (float) $this->installment);
    }
}
