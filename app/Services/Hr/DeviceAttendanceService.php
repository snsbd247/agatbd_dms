<?php

namespace App\Services\Hr;

use App\Models\Hr\Attendance;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Punches pushed by attendance machines (ZKTeco "ADMS" / iclock protocol).
 *
 * Each punch is stored once. A day's attendance is then rebuilt from all of
 * that person's punches on the day: the first is "in", the last is "out".
 * A day someone corrected by hand (source "manual") is left alone. Punches
 * from a machine user id no employee has yet stay pending until one does.
 */
class DeviceAttendanceService
{
    public function __construct(private AttendanceService $attendance) {}

    /**
     * Read an ATTLOG upload: one punch per line, tab-separated
     * "user id, yyyy-mm-dd hh:mm:ss, status, verify, ...".
     *
     * @return int punches read (new or already stored)
     */
    public function ingest(AttendanceDevice $device, string $body): int
    {
        $read = 0;
        $new = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($body)) as $line) {
            $cols = preg_split('/\t+/', trim($line));
            if (count($cols) < 2 || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}(:\d{2})?$/', $cols[1])) {
                continue;
            }
            $userId = substr(trim($cols[0]), 0, 30);
            if ($userId === '') {
                continue;
            }
            try {
                $at = Carbon::parse($cols[1]);
            } catch (\Throwable) {
                continue;
            }
            $read++;

            $punch = AttendancePunch::firstOrCreate(
                ['device_user_id' => $userId, 'punched_at' => $at->format('Y-m-d H:i:s')],
                ['attendance_device_id' => $device->id],
            );
            if ($punch->wasRecentlyCreated) {
                $new[] = $punch;
            }
        }

        if ($new) {
            $device->increment('punch_count', count($new));
            $this->process(collect($new));
        }

        return $read;
    }

    /** Turn pending punches (all, or the given ones) into attendance days. Returns days saved. */
    public function process($punches = null): int
    {
        $punches ??= AttendancePunch::where('processed', false)->orderBy('punched_at')->get();
        if ($punches->isEmpty()) {
            return 0;
        }

        $employees = Employee::whereNotNull('device_user_id')->get()->keyBy(fn ($e) => strtolower($e->device_user_id));
        $days = [];

        foreach ($punches as $punch) {
            $employee = $employees[strtolower($punch->device_user_id)] ?? null;
            if (! $employee) {
                continue;   // stays pending until an employee gets this machine id
            }
            $days[$employee->id.'|'.$punch->punched_at->toDateString()] = $employee;
        }

        $saved = 0;
        foreach ($days as $key => $employee) {
            $date = explode('|', $key)[1];
            DB::transaction(function () use ($employee, $date, &$saved) {
                $dayPunches = AttendancePunch::whereRaw('LOWER(device_user_id) = ?', [strtolower($employee->device_user_id)])
                    ->whereBetween('punched_at', [$date.' 00:00:00', $date.' 23:59:59'])
                    ->orderBy('punched_at')->get();

                AttendancePunch::whereIn('id', $dayPunches->pluck('id'))->update(['employee_id' => $employee->id, 'processed' => true]);

                $existing = Attendance::where('employee_id', $employee->id)->whereDate('date', $date)->first();
                if ($existing && $existing->source === 'manual') {
                    return;   // corrected by hand: keep it
                }

                $in = $dayPunches->first()->punched_at->format('H:i');
                $out = $dayPunches->count() > 1 ? $dayPunches->last()->punched_at->format('H:i') : null;
                $this->attendance->record($employee, $date, $in, $out, null, 'device');
                $saved++;
            });
        }

        return $saved;
    }
}
