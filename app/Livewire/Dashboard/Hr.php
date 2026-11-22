<?php

namespace App\Livewire\Dashboard;

use App\Models\Hr\Attendance;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\LeaveApplication;
use App\Models\Hr\PayrollRun;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** People at a glance: headcount, today's attendance, leave, salary and loans. */
#[Layout('layouts.app')]
#[Title('HR Dashboard')]
class Hr extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.view') && ! auth()->user()->dealer_id, 403);
    }

    public function render()
    {
        $today = today()->toDateString();
        $active = Employee::where('status', 'active');

        $onLeave = LeaveApplication::where('status', 'approved')->whereDate('from_date', '<=', $today)->whereDate('to_date', '>=', $today)->distinct()->count('employee_id');
        $marked = Attendance::whereDate('date', $today)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $tracked = (clone $active)->where('track_attendance', true)->count();
        $present = (int) ($marked['present'] ?? 0) + (int) ($marked['late'] ?? 0) + (int) ($marked['half_day'] ?? 0);

        $run = PayrollRun::where('period', today()->format('Y-m'))->latest('id')->first()
            ?? PayrollRun::latest('period')->latest('id')->first();

        return view('livewire.dashboard.hr', [
            'stats' => [
                'employees' => (clone $active)->count(),
                'by_category' => (clone $active)->selectRaw('category, COUNT(*) as n')->groupBy('category')->pluck('n', 'category'),
                'tracked' => $tracked,
                'present' => $present,
                'late' => (int) ($marked['late'] ?? 0),
                'on_leave' => $onLeave,
                'not_in' => max(0, $tracked - $present - $onLeave),
                'leave_pending' => LeaveApplication::where('status', 'pending')->count(),
                'loans_out' => (float) EmployeeLoan::where('status', 'open')->selectRaw('COALESCE(SUM(amount - recovered), 0) as v')->value('v'),
                'loans_open' => EmployeeLoan::where('status', 'open')->count(),
                'joined' => Employee::whereDate('join_date', '>=', today()->startOfMonth()->toDateString())->count(),
                'devices' => AttendanceDevice::where('active', true)->count(),
                'devices_online' => AttendanceDevice::where('active', true)->where('last_seen_at', '>', now()->subMinutes(10))->count(),
            ],
            'run' => $run,
            'runTotal' => $run ? (float) $run->payslips()->sum('net') : 0.0,
            'leaves' => LeaveApplication::with(['employee', 'leaveType'])->where('status', 'pending')->orderBy('from_date')->limit(6)->get(),
            'lateToday' => Attendance::with('employee')->whereDate('date', $today)->where('status', 'late')->orderBy('in_time')->limit(8)->get(),
        ]);
    }
}
