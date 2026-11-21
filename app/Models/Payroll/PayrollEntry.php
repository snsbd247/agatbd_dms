<?php

namespace App\Models\Payroll;

use App\Models\Representative;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'representative_id';

    use Auditable;

    protected $fillable = [
        'number', 'representative_id', 'period', 'base_salary', 'commission_amount',
        'bonus_amount', 'advance_deduction', 'other_deduction', 'net_salary', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'advance_deduction' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }
}
