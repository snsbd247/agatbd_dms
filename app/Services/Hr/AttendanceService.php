<?php

namespace App\Services\Hr;

use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveApplication;
use App\Models\Hr\Shift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Attendance rules and import.
 *
 * Late = in after shift start + grace. Overtime (for overtime-eligible staff)
 * = time after the shift's end. Import reads a CSV either with one row per
 * person per day (code, date, in, out) or a punch log as exported by a
 * ZKTeco device (user id, date-time — one row per punch): the first punch
 * of the day is "in", the last is "out".
 */
class AttendanceService
{
    /** @return array{status: string, overtime_minutes: int} */
    public function evaluate(Employee $employee, ?string $in, ?string $out): array
    {
        if (! $in) {
            return ['status' => 'absent', 'overtime_minutes' => 0];
        }

        $shift = $employee->shift ?? Shift::orderBy('id')->first();
        $status = 'present';
        $overtime = 0;

        if ($shift) {
            $start = Carbon::parse($shift->start_time)->addMinutes((int) $shift->grace_minutes);
            if (Carbon::parse($in)->gt($start)) {
                $status = 'late';
            }
            if ($out && $employee->overtime_eligible) {
                $overtime = max(0, (int) Carbon::parse($shift->end_time)->diffInMinutes(Carbon::parse($out), false));
            }
            // Less than half the shift worked counts as a half day.
            if ($out) {
                $shiftMinutes = Carbon::parse($shift->start_time)->diffInMinutes(Carbon::parse($shift->end_time));
                if (Carbon::parse($in)->diffInMinutes(Carbon::parse($out)) < $shiftMinutes / 2) {
                    $status = 'half_day';
                }
            }
        }

        return ['status' => $status, 'overtime_minutes' => $overtime];
    }

    /** Save one day's attendance (by hand or import). */
    public function record(Employee $employee, string $date, ?string $in, ?string $out, ?string $status = null, string $source = 'manual', ?string $note = null): Attendance
    {
        $auto = $this->evaluate($employee, $in, $out);

        // whereDate: the date cast may store a time part (SQLite), so plain equality would miss the row.
        $record = Attendance::where('employee_id', $employee->id)->whereDate('date', $date)->first()
            ?? new Attendance(['employee_id' => $employee->id, 'date' => $date]);

        $record->fill([
            'in_time' => $in ?: null,
            'out_time' => $out ?: null,
            'status' => $status ?: $auto['status'],
            'overtime_minutes' => $auto['overtime_minutes'],
            'source' => $source,
            'note' => $note,
        ])->save();

        return $record;
    }

    /**
     * @return array{saved: int, skipped: array<int, string>}
     */
    public function import(string $csv): array
    {
        $rows = array_values(array_filter(array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($csv))), fn ($r) => count(array_filter($r, fn ($c) => trim((string) $c) !== '')) > 0));
        if ($rows === []) {
            return ['saved' => 0, 'skipped' => [__('The file is empty.')]];
        }

        // Header row?
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
        $hasHeader = (bool) array_intersect($header, ['code', 'employee_code', 'id', 'user_id', 'pin', 'date', 'in', 'out', 'datetime', 'time']);
        if ($hasHeader) {
            array_shift($rows);
        }

        $employees = Employee::all();
        $byCode = $employees->keyBy(fn ($e) => strtolower($e->employee_code));
        $byDevice = $employees->filter(fn ($e) => $e->device_user_id)->keyBy(fn ($e) => strtolower($e->device_user_id));

        $days = [];   // "empId|date" => [in, out]
        $skipped = [];

        foreach ($rows as $i => $row) {
            $row = array_map(fn ($c) => trim((string) $c), $row);
            $id = strtolower($row[0] ?? '');
            $employee = $byDevice[$id] ?? $byCode[$id] ?? null;
            if (! $employee) {
                $skipped[] = __('Line :n: no employee with code or device id ":id".', ['n' => $i + 1 + ($hasHeader ? 1 : 0), 'id' => $row[0] ?? '']);
                continue;
            }

            try {
                if (count($row) >= 4 && $row[2] !== '' && ! str_contains($row[1], ':')) {
                    // code, date, in, out
                    $date = Carbon::parse($row[1])->toDateString();
                    $punches = array_filter([$row[2], $row[3] ?? '']);
                } else {
                    // punch log: id, "date time" (or id, date, time)
                    $stamp = Carbon::parse(isset($row[2]) && $row[2] !== '' && ! str_contains($row[1], ':') ? $row[1].' '.$row[2] : $row[1]);
                    $date = $stamp->toDateString();
                    $punches = [$stamp->format('H:i')];
                }
            } catch (\Throwable) {
                $skipped[] = __('Line :n: the date or time cannot be read.', ['n' => $i + 1 + ($hasHeader ? 1 : 0)]);
                continue;
            }

            $key = $employee->id.'|'.$date;
            foreach ($punches as $p) {
                $t = Carbon::parse($p)->format('H:i');
                $days[$key][] = $t;
            }
        }

        $saved = 0;
        foreach ($days as $key => $times) {
            [$empId, $date] = explode('|', $key);
            sort($times);
            $in = $times[0];
            $out = count($times) > 1 ? end($times) : null;
            $this->record($employees->firstWhere('id', (int) $empId), $date, $in, $out, null, 'csv');
            $saved++;
        }

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    /**
     * Days of a month for one employee, within the time they were employed.
     *
     * @return array{month_days: int, payable_days: int, working_days: float, present: float, late: int, paid_leave: float, unpaid_leave: float, absent: float, overtime_minutes: int}
     */
    public function monthSummary(Employee $employee, string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $from = $employee->join_date && $employee->join_date->gt($start) ? $employee->join_date->copy() : $start->copy();
        $to = $employee->exit_date && $employee->exit_date->lt($end) ? $employee->exit_date->copy() : $end->copy();

        $empty = ['month_days' => $start->daysInMonth, 'payable_days' => 0, 'working_days' => 0.0, 'present' => 0.0, 'late' => 0, 'paid_leave' => 0.0, 'unpaid_leave' => 0.0, 'absent' => 0.0, 'overtime_minutes' => 0];
        if ($from->gt($to)) {
            return $empty;
        }

        $weeklyOff = strtolower($employee->shift?->weekly_off ?? Shift::orderBy('id')->value('weekly_off') ?? 'friday');
        $holidays = Holiday::whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();

        $workingDates = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            if (strtolower($day->englishDayOfWeek) !== $weeklyOff && ! in_array($day->toDateString(), $holidays, true)) {
                $workingDates[] = $day->toDateString();
            }
        }

        $paidLeave = $unpaidLeave = 0.0;
        $leaves = LeaveApplication::with('leaveType')->where('employee_id', $employee->id)->where('status', 'approved')
            ->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->get();
        $leaveDates = [];
        foreach ($leaves as $leave) {
            foreach (CarbonPeriod::create($leave->from_date, $leave->to_date) as $d) {
                $ds = $d->toDateString();
                if (in_array($ds, $workingDates, true) && ! isset($leaveDates[$ds])) {
                    $leaveDates[$ds] = true;
                    $leave->leaveType?->paid ? $paidLeave++ : $unpaidLeave++;
                }
            }
        }

        $present = 0.0;
        $late = 0;
        $overtime = 0;
        if ($employee->track_attendance) {
            $records = Attendance::where('employee_id', $employee->id)->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->get();
            foreach ($records as $r) {
                $present += match ($r->status) {
                    'present' => 1.0,
                    'late' => 1.0,
                    'half_day' => 0.5,
                    default => 0.0,
                };
                $late += $r->status === 'late' ? 1 : 0;
                $overtime += $employee->overtime_eligible ? (int) $r->overtime_minutes : 0;
            }
            $absent = max(0.0, count($workingDates) - $present - $paidLeave - $unpaidLeave);
        } else {
            // Not tracked (e.g. field staff): present every working day not on leave.
            $present = max(0.0, count($workingDates) - $paidLeave - $unpaidLeave);
            $absent = 0.0;
        }

        return [
            'month_days' => $start->daysInMonth,
            'payable_days' => (int) $from->diffInDays($to) + 1,
            'working_days' => (float) count($workingDates),
            'present' => $present,
            'late' => $late,
            'paid_leave' => $paidLeave,
            'unpaid_leave' => $unpaidLeave,
            'absent' => $absent,
            'overtime_minutes' => $overtime,
        ];
    }
}
