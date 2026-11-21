<?php

namespace App\Livewire\Hr\Payroll;

use App\Models\Hr\PayrollRun;
use App\Services\Hr\PayrollService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Salary Sheets')]
class Index extends Component
{
    public string $period = '';

    public function mount(): void
    {
        $this->authorize('hr.view');
        $this->period = today()->format('Y-m');
    }

    public function generate(PayrollService $payroll): void
    {
        $this->authorize('hr.manage');
        $this->validate(['period' => 'required|date_format:Y-m']);

        try {
            $run = $payroll->generate($this->period);
        } catch (\RuntimeException $e) {
            $this->addError('period', $e->getMessage());

            return;
        }

        $this->redirect(route('hr.payroll.show', $run), navigate: false);
    }

    public function render()
    {
        return view('livewire.hr.payroll.index', [
            'runs' => PayrollRun::withCount('payslips')->withSum('payslips', 'net')->withSum('payslips', 'gross')->orderByDesc('period')->get(),
        ]);
    }
}
