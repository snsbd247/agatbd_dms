<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LeaveApplication extends Model
{
    use Auditable;
    use \App\Models\Concerns\BelongsToTenant;

    public const TENANT_PATH = 'employee_id';


    protected $fillable = ['employee_id', 'leave_type_id', 'from_date', 'to_date', 'days', 'reason', 'status', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'decided_at' => 'datetime',
            'days' => 'decimal:1',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function decider()
    {
        return $this->belongsTo(\App\Models\User::class, 'decided_by');
    }
}
