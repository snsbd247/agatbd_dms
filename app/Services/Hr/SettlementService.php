<?php

namespace App\Services\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\FinalSettlement;
use App\Models\Hr\LeaveApplication;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Payslip;
use App\Models\Payroll\Advance;
use App\Services\Accounts\AutoPosting;
use App\Services\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What is paid when someone leaves:
 *  - salary for the days worked in the last month (if that month is not on a salary sheet yet),
 *  - unused earned leave at basic ÷ 30 a day,
 *  - gratuity: 30 days' basic for every completed year of service,
 *  less advances and loans still outstanding.
 */
class SettlementService
{
    public function __construct(private NumberGeneratorService $numbers, private PayrollService $payroll) {}

    /** @return array<string, mixed> the figures, without saving */
    public function calculate(Employee $employee, string $exitDate, bool $gratuity = true): array
    {
        $exit = Carbon::parse($exitDate);
        $period = $exit->format('Y-m');
        $daily = (float) $employee->basic / 30;
        $grossDaily = $employee->gross() / 30;

        $onSheet = Payslip::where('employee_id', $employee->id)->whereHas('run', fn ($q) => $q->where('period', $period))->exists();
        $salaryDays = $onSheet ? 0 : $exit->day;

        // Earned leave: the yearly entitlement for this year of service, less what was taken.
        $el = LeaveType::where('code', 'EL')->first();
        $yearStart = $exit->copy()->startOfYear()->max($employee->join_date);
        $entitled = $el ? $el->days_per_year * ($yearStart->diffInDays($exit) + 1) / ($exit->copy()->startOfYear()->diffInDays($exit->copy()->endOfYear()) + 1) : 0;
        $taken = $el ? (float) LeaveApplication::where('employee_id', $employee->id)->where('leave_type_id', $el->id)->where('status', 'approved')
            ->whereDate('from_date', '>=', $yearStart)->sum('days') : 0;
        $leaveDays = round(max(0, $entitled - $taken), 1);

        $years = round($employee->join_date->floatDiffInYears($exit), 2);
        $completed = (int) floor($years);

        $loans = (float) EmployeeLoan::where('employee_id', $employee->id)->where('status', 'open')->get()->sum(fn ($l) => $l->remaining());
        if ($employee->representative_id) {
            $loans += (float) Advance::where('representative_id', $employee->representative_id)->where('deducted', false)->sum('amount');
        }

        $salary = round($salaryDays * $grossDaily, 2);
        $encash = round($leaveDays * $daily, 2);
        $grat = $gratuity ? round($completed * 30 * $daily, 2) : 0.0;
        $payable = $salary + $encash + $grat;
        $loanDeduction = round(min($loans, $payable), 2);

        return [
            'employee_id' => $employee->id,
            'exit_date' => $exit->toDateString(),
            'salary_days' => $salaryDays,
            'salary_amount' => $salary,
            'leave_days' => $leaveDays,
            'leave_encashment' => $encash,
            'service_years' => $years,
            'gratuity' => $grat,
            'other_payable' => 0,
            'loan_deduction' => $loanDeduction,
            'other_deduction' => 0,
            'net' => round($payable - $loanDeduction, 2),
            'outstanding_loans' => round($loans, 2),
        ];
    }

    public function create(Employee $employee, string $exitDate, string $reason, bool $gratuity, float $otherPayable, float $otherDeduction, ?string $note): FinalSettlement
    {
        if ($employee->settlement()->exists()) {
            throw new \RuntimeException(__('This employee already has a final settlement.'));
        }

        $f = $this->calculate($employee, $exitDate, $gratuity);
        unset($f['outstanding_loans']);
        $f['other_payable'] = round(max(0, $otherPayable), 2);
        $f['other_deduction'] = round(max(0, $otherDeduction), 2);
        $f['net'] = round($f['salary_amount'] + $f['leave_encashment'] + $f['gratuity'] + $f['other_payable'] - $f['loan_deduction'] - $f['other_deduction'], 2);

        if ($f['net'] < 0) {
            throw new \RuntimeException(__('Deductions cannot be more than the amount payable.'));
        }

        return DB::transaction(function () use ($employee, $f, $reason, $note) {
            $employee->update(['exit_date' => $f['exit_date'], 'status' => $reason]);

            return FinalSettlement::create($f + [
                'number' => $this->numbers->generate('final_settlement'),
                'reason' => $reason,
                'status' => 'draft',
                'note' => $note,
            ]);
        });
    }

    public function pay(FinalSettlement $settlement, string $method): void
    {
        if ($settlement->status !== 'draft') {
            throw new \RuntimeException(__('This settlement is already paid.'));
        }

        DB::transaction(function () use ($settlement, $method) {
            // The deduction clears advances first (old representative ones too), the rest goes to loans.
            $employee = $settlement->employee;
            $advances = (float) EmployeeLoan::where('employee_id', $employee->id)->where('type', 'advance')->where('status', 'open')->get()->sum(fn ($l) => $l->remaining());
            if ($employee->representative_id) {
                $advances += (float) Advance::where('representative_id', $employee->representative_id)->where('deducted', false)->sum('amount');
            }
            $toAdvances = min((float) $settlement->loan_deduction, $advances);
            $this->payroll->recover($employee, $toAdvances, (float) $settlement->loan_deduction - $toAdvances);

            $settlement->update(['status' => 'paid', 'paid_at' => now(), 'paid_from' => $method]);
            app(AutoPosting::class)->finalSettlement($settlement->fresh());
        });
    }
}
