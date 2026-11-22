<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/**
 * An attendance machine (ZKTeco or another ADMS-capable device) that sends
 * its punches to /iclock/cdata. Only active, registered serial numbers are
 * accepted; an unknown machine is added inactive so an admin can approve it.
 */
class AttendanceDevice extends Model
{
    protected $fillable = ['serial_no', 'name', 'location', 'active', 'last_seen_at', 'last_ip', 'punch_count'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function punches()
    {
        return $this->hasMany(AttendancePunch::class);
    }

    /** Heard from in the last 10 minutes (the device checks in every few seconds). */
    public function online(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(10));
    }
}
