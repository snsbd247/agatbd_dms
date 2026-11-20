<?php

namespace App\Livewire\Payroll\Entries;

use App\Models\Payroll\Commission;
use App\Models\Payroll\PayrollEntry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Payroll Entry')]
class Show extends Component
{
    public PayrollEntry $payrollEntry;

    public function markPaid(): void
    {
        $this->authorize('payroll.manage');

        if ($this->payrollEntry->status !== 'pending') {
            return;
        }

        $this->payrollEntry->update(['status' => 'paid', 'paid_at' => now()]);
    }

    public function render()
    {
        $this->payrollEntry->load('representative');

        $commission = Commission::where('representative_id', $this->payrollEntry->representative_id)
            ->where('period', $this->payrollEntry->period)
            ->first();

        return view('livewire.payroll.entries.show', compact('commission'));
    }
}
