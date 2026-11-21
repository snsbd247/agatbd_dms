<?php

namespace App\Livewire\Hr\Payroll;

use App\Models\Hr\PayrollRun;
use App\Models\Hr\Payslip;
use App\Services\Hr\PayrollService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public PayrollRun $run;

    public ?int $editingId = null;
    public string $bonus = '0';
    public string $other_deduction = '0';
    public string $note = '';

    public string $pay_method = 'bank';

    public function mount(PayrollRun $run): void
    {
        $this->authorize('hr.view');
        $this->run = $run;
    }

    public function edit(int $id): void
    {
        $this->authorize('hr.manage');
        $slip = $this->run->payslips()->findOrFail($id);
        $this->editingId = $slip->id;
        $this->bonus = (string) (float) $slip->bonus;
        $this->other_deduction = (string) (float) $slip->other_deduction;
        $this->note = (string) $slip->note;
    }

    public function saveSlip(PayrollService $payroll): void
    {
        $this->authorize('hr.manage');
        $this->validate(['bonus' => 'required|numeric|min:0', 'other_deduction' => 'required|numeric|min:0', 'note' => 'nullable|string|max:255']);
        $this->act(fn () => $payroll->updateSlip(Payslip::findOrFail($this->editingId), (float) $this->bonus, (float) $this->other_deduction, $this->note ?: null));
        if (! $this->getErrorBag()->has('action')) {
            $this->editingId = null;
        }
    }

    public function refreshSheet(PayrollService $payroll): void
    {
        $this->authorize('hr.manage');
        $this->act(fn () => $payroll->refresh($this->run));
    }

    public function approve(PayrollService $payroll): void
    {
        $this->authorize('hr.payroll');
        $this->act(fn () => $payroll->approve($this->run));
    }

    public function reopen(PayrollService $payroll): void
    {
        $this->authorize('hr.payroll');
        $this->act(fn () => $payroll->reopen($this->run));
    }

    public function pay(PayrollService $payroll): void
    {
        $this->authorize('hr.payroll');
        $this->validate(['pay_method' => 'required|in:cash,bank,mobile']);
        $this->act(fn () => $payroll->pay($this->run, $this->pay_method));
    }

    public function discard(PayrollService $payroll): void
    {
        $this->authorize('hr.manage');
        if ($this->act(fn () => $payroll->discard($this->run))) {
            $this->redirect(route('hr.payroll.index'), navigate: false);
        }
    }

    private function act(callable $fn): bool
    {
        try {
            $fn();
            $this->run->refresh();

            return true;
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());

            return false;
        }
    }

    public function render()
    {
        $slips = $this->run->payslips()->with('employee.department')->get()->sortBy(fn ($s) => $s->employee->employee_code)->values();
        $sum = fn (string $f) => (float) $slips->sum(fn ($s) => (float) $s->{$f});

        return view('livewire.hr.payroll.show', [
            'slips' => $slips,
            'totals' => collect(['basic', 'house_rent', 'medical', 'conveyance', 'other_allowance', 'overtime_amount', 'commission', 'bonus', 'gross', 'absent_deduction', 'advance_deduction', 'loan_deduction', 'other_deduction', 'net'])
                ->mapWithKeys(fn ($f) => [$f => $sum($f)])->all(),
            'voucher' => $this->run->status === 'paid'
                ? \App\Models\Accounts\JournalEntry::where('source_type', $this->run->getMorphClass())->where('source_id', $this->run->id)->first()
                : null,
        ])->title(__('Salary sheet').' — '.$this->run->periodLabel());
    }
}
