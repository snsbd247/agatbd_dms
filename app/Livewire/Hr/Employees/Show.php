<?php

namespace App\Livewire\Hr\Employees;

use App\Models\Hr\Employee;
use App\Models\Hr\LeaveApplication;
use App\Models\Hr\LeaveType;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\SettlementService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Employee $employee;

    public string $period = '';

    // Final settlement form
    public bool $showSettlement = false;
    public string $exit_date = '';
    public string $reason = 'resigned';
    public bool $gratuity = true;
    public string $other_payable = '0';
    public string $other_deduction = '0';
    public string $settlement_note = '';
    public string $pay_method = 'cash';

    public function mount(Employee $employee): void
    {
        $this->authorize('hr.view');
        $this->employee = $employee;
        $this->period = today()->format('Y-m');
        $this->exit_date = today()->toDateString();
        $this->pay_method = $employee->payment_method ?: 'cash';
    }

    public function createSettlement(SettlementService $settlements): void
    {
        $this->authorize('hr.manage');
        $this->validate([
            'exit_date' => 'required|date|after_or_equal:'.$this->employee->join_date->toDateString(),
            'reason' => 'required|in:resigned,terminated',
            'other_payable' => 'nullable|numeric|min:0',
            'other_deduction' => 'nullable|numeric|min:0',
        ]);

        try {
            $settlements->create($this->employee, $this->exit_date, $this->reason, $this->gratuity, (float) $this->other_payable, (float) $this->other_deduction, $this->settlement_note ?: null);
            $this->showSettlement = false;
        } catch (\RuntimeException $e) {
            $this->addError('settlement', $e->getMessage());
        }
    }

    public function paySettlement(SettlementService $settlements): void
    {
        $this->authorize('hr.payroll');
        try {
            $settlements->pay($this->employee->settlement, $this->pay_method);
        } catch (\RuntimeException $e) {
            $this->addError('settlement', $e->getMessage());
        }
    }

    public function render(AttendanceService $attendance, SettlementService $settlements)
    {
        $e = $this->employee->load(['department', 'designation', 'shift', 'factory', 'representative', 'user', 'settlement']);
        $yearStart = today()->startOfYear();

        $taken = LeaveApplication::where('employee_id', $e->id)->where('status', 'approved')
            ->whereDate('from_date', '>=', $yearStart)->get()->groupBy('leave_type_id')->map(fn ($g) => (float) $g->sum('days'));

        return view('livewire.hr.employees.show', [
            'summary' => $attendance->monthSummary($e, $this->period),
            'leaveTypes' => LeaveType::where('active', true)->orderBy('id')->get(),
            'taken' => $taken,
            'loans' => $e->loans()->orderByDesc('issue_date')->get(),
            'payslips' => $e->payslips()->with('run')->latest('id')->limit(12)->get(),
            'preview' => $this->showSettlement && ! $e->settlement ? $settlements->calculate($e, $this->exit_date ?: today()->toDateString(), $this->gratuity) : null,
        ])->title($e->name);
    }
}
