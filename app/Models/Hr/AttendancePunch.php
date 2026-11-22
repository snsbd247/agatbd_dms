<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** One finger/face/card punch from a machine, kept as received. */
class AttendancePunch extends Model
{
    protected $fillable = ['attendance_device_id', 'device_user_id', 'punched_at', 'employee_id', 'processed'];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'processed' => 'boolean',
        ];
    }

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
