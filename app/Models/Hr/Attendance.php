<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use Auditable;
    use \App\Models\Concerns\BelongsToTenant;

    public const TENANT_PATH = 'employee_id';


    protected $fillable = ['employee_id', 'date', 'in_time', 'out_time', 'status', 'overtime_minutes', 'source', 'note'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public const STATUSES = [
        'present' => 'Present',
        'late' => 'Late',
        'half_day' => 'Half day',
        'absent' => 'Absent',
        'leave' => 'On leave',
        'holiday' => 'Holiday',
        'weekly_off' => 'Weekly off',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
