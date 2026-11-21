<?php

namespace App\Services\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\PayrollRun;
use App\Models\Hr\Payslip;
use App\Models\Payroll\Advance;
use App\Models\Payroll\Bonus;
use App\Models\Payroll\PayrollEntry;
use App\Services\Accounts\AutoPosting;
use App\Services\NumberGeneratorService;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The monthly salary sheet.
 *
 *  - Pay is for the days employed in the month (joiners and leavers get a
 *    share of the month).
 *  - Absence and unpaid leave cost basic ÷ 30 per day; overtime is paid at
 *    basic ÷ 208 × 2 per hour (Bangladesh Labour Act).
 *  - Marketing staff get their commission for the month and any bonus.
 *  - Advances are taken back in full, loans by their installment, never
 *    more than the pay.
 *
 * draft (editable: bonus, other deduction) → approved → paid (posted to the
 * accounts, advances and loans marked as recovered).
 */
class PayrollService
{
    public function __construct(
        private AttendanceService $attendance,
        private CommissionService $commission,
        private NumberGeneratorService $numbers,
    ) {}

    public function generate(string $period): PayrollRun
    {
        if (PayrollRun::where('period', $period)->exists()) {
            throw new \RuntimeException(__('A salary sheet for :p already exists.', ['p' => $period]));
        }

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $employees = Employee::with(['shift', 'representative'])
            ->whereDate('join_date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('exit_date')->orWhereDate('exit_date', '>=', $start))
            ->where(fn ($q) => $q->where('status', 'active')->orWhereDate('exit_date', '>=', $start))
            ->whereDoesntHave('settlement')
            ->orderBy('employee_code')
            ->get();

        if ($employees->isEmpty()) {
            throw new \RuntimeException(__('No employees to pay for :p.', ['p' => $period]));
        }

        // Representatives already paid through the old representative payroll this month.
        $paidOld = PayrollEntry::where('period', $period)->pluck('representative_id')->all();

        return DB::transaction(function () use ($employees, $period, $paidOld) {
            $run = PayrollRun::create([
                'dealer_id' => Tenant::dealerId(),
                'number' => $this->numbers->generate('salary_sheet'),
                'period' => $period,
                'status' => 'draft',
            ]);

            foreach ($employees as $employee) {
                if ($employee->representative_id && in_array($employee->representative_id, $paidOld, true)) {
                    continue;
                }
                $run->payslips()->create($this->compute($employee, $period));
            }

            return $run;
        });
    }

    /** Figures of one employee's payslip for a month. */
    public function compute(Employee $employee, string $period): array
    {
        $s = $this->attendance->monthSummary($employee, $period);
        $share = $s['month_days'] > 0 ? $s['payable_days'] / $s['month_days'] : 0;
        $money = fn ($v) => round((float) $v * $share, 2);

        $absentDays = $s['absent'] + $s['unpaid_leave'];
        $overtimeHours = round($s['overtime_minutes'] / 60, 2);

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $commission = 0.0;
        $bonus = 0.0;
        $oldAdvances = 0.0;
        if ($rep = $employee->representative) {
            $commission = (float) $this->commission->forPeriod($rep, $period)->commission_amount;
            $bonus = (float) Bonus::where('representative_id', $rep->id)->whereBetween('bonus_date', [$start, $end])->sum('amount');
            $oldAdvances = (float) Advance::where('representative_id', $rep->id)->where('deducted', false)->sum('amount');
        }

        $slip = new Payslip([
            'employee_id' => $employee->id,
            'month_days' => $s['month_days'],
            'payable_days' => $s['payable_days'],
            'working_days' => $s['working_days'],
            'present_days' => $s['present'],
            'paid_leave_days' => $s['paid_leave'],
            'unpaid_leave_days' => $s['unpaid_leave'],
            'absent_days' => $s['absent'],
            'basic' => $money($employee->basic),
            'house_rent' => $money($employee->house_rent),
            'medical' => $money($employee->medical),
            'conveyance' => $money($employee->conveyance),
            'other_allowance' => $money($employee->other_allowance),
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => round($overtimeHours * $employee->overtimeHourlyRate(), 2),
            'commission' => $commission,
            'bonus' => $bonus,
            'absent_deduction' => round($absentDays * $employee->dailyBasic(), 2),
            'other_deduction' => 0,
        ]);

        // Advances and loans, without taking more than what is left of the pay.
        $slip->advance_deduction = 0;
        $slip->loan_deduction = 0;
        $slip->recalculate();
        $room = max(0.0, (float) $slip->net);

        $loans = EmployeeLoan::where('employee_id', $employee->id)->where('status', 'open')->orderByRaw("CASE WHEN type = 'advance' THEN 0 ELSE 1 END")->orderBy('issue_date')->get();
        $advance = min($room, $oldAdvances + (float) $loans->where('type', 'advance')->sum(fn ($l) => $l->nextDeduction()));
        $room -= $advance;
        $loan = min($room, (float) $loans->where('type', 'loan')->sum(fn ($l) => $l->nextDeduction()));

        $slip->advance_deduction = round($advance, 2);
        $slip->loan_deduction = round($loan, 2);
        $slip->recalculate();

        return $slip->getAttributes();
    }

    /** Recompute every payslip of a draft sheet (after attendance or leave changed). */
    public function refresh(PayrollRun $run): void
    {
        $this->assertStatus($run, 'draft');

        DB::transaction(function () use ($run) {
            foreach ($run->payslips()->with('employee.shift', 'employee.representative')->get() as $slip) {
                $keep = ['bonus' => (float) $slip->bonus, 'other_deduction' => (float) $slip->other_deduction, 'note' => $slip->note];
                $fresh = $this->compute($slip->employee, $run->period);
                $slip->fill($fresh);
                // Hand-entered bonus and other deduction survive a refresh.
                $slip->bonus = max((float) $fresh['bonus'], $keep['bonus']);
                $slip->other_deduction = $keep['other_deduction'];
                $slip->note = $keep['note'];
                $slip->recalculate();
                $slip->save();
            }
        });
    }

    public function updateSlip(Payslip $slip, float $bonus, float $otherDeduction, ?string $note): void
    {
        $this->assertStatus($slip->run, 'draft');

        $slip->bonus = round(max(0, $bonus), 2);
        $slip->other_deduction = round(max(0, $otherDeduction), 2);
        $slip->note = $note;
        $slip->recalculate();

        if ((float) $slip->net < 0) {
            throw new \RuntimeException(__('Deductions cannot be more than the pay.'));
        }

        $slip->save();
    }

    public function approve(PayrollRun $run): void
    {
        $this->assertStatus($run, 'draft');
        $run->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now()]);
    }

    public function reopen(PayrollRun $run): void
    {
        $this->assertStatus($run, 'approved');
        $run->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
    }

    /** Pay the approved sheet from cash, bank or mobile money. */
    public function pay(PayrollRun $run, string $method): void
    {
        $this->assertStatus($run, 'approved');

        DB::transaction(function () use ($run, $method) {
            foreach ($run->payslips()->with('employee')->get() as $slip) {
                $this->recover($slip->employee, (float) $slip->advance_deduction, (float) $slip->loan_deduction);
            }

            $run->update(['status' => 'paid', 'paid_at' => now(), 'paid_from' => $method]);
            app(AutoPosting::class)->salarySheet($run->fresh('payslips'));
        });
    }

    public function discard(PayrollRun $run): void
    {
        $this->assertStatus($run, 'draft');
        $run->delete();
    }

    /** Mark advances (old representative ones first) and loans as taken back. */
    public function recover(Employee $employee, float $advance, float $loan): void
    {
        if ($employee->representative_id && $advance > 0) {
            foreach (Advance::where('representative_id', $employee->representative_id)->where('deducted', false)->orderBy('advance_date')->get() as $old) {
                if ($advance + 0.005 < (float) $old->amount) {
                    break;
                }
                $old->update(['deducted' => true]);
                $advance -= (float) $old->amount;
            }
        }

        foreach (['advance' => $advance, 'loan' => $loan] as $type => $amount) {
            foreach (EmployeeLoan::where('employee_id', $employee->id)->where('type', $type)->where('status', 'open')->orderBy('issue_date')->get() as $l) {
                if ($amount <= 0.005) {
                    break;
                }
                $take = min($amount, $l->remaining());
                $l->recovered = round((float) $l->recovered + $take, 2);
                $l->status = $l->remaining() <= 0.005 ? 'closed' : 'open';
                $l->save();
                $amount -= $take;
            }
        }
    }

    private function assertStatus(PayrollRun $run, string $status): void
    {
        if ($run->status !== $status) {
            throw new \RuntimeException(__('This salary sheet is :s.', ['s' => __(ucfirst($run->status))]));
        }
    }
}
