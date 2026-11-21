<?php

namespace App\Livewire\Payroll\Entries;

use App\Models\Payroll\Advance;
use App\Models\Payroll\Bonus;
use App\Models\Payroll\Commission;
use App\Models\Payroll\PayrollEntry;
use App\Models\Payroll\RepresentativeTarget;
use App\Models\Representative;
use App\Models\Returns\ReturnRequestItem;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Generate Payroll')]
class Create extends Component
{
    public string $period;

    public function mount(): void
    {
        $this->authorize('payroll.manage');
        $this->period = today()->format('Y-m');
    }

    public function generate(NumberGeneratorService $numbers): void
    {
        $this->authorize('payroll.manage');

        $this->validate(['period' => 'required|date_format:Y-m']);

        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $existingRepIds = PayrollEntry::where('period', $this->period)->pluck('representative_id');

        // Representatives who are HR employees are paid on the HR salary sheet instead.
        $onHrSheet = \App\Models\Hr\Employee::whereNotNull('representative_id')->pluck('representative_id');

        $representatives = Representative::where('active', true)
            ->whereNotIn('id', $existingRepIds)
            ->whereNotIn('id', $onHrSheet)
            ->get();

        if ($representatives->isEmpty()) {
            $this->addError('period', __('Every active representative already has a payroll entry for this period, or is paid on the HR salary sheet.'));

            return;
        }

        DB::transaction(function () use ($representatives, $start, $end, $numbers) {
            foreach ($representatives as $rep) {
                $commissionAmount = (float) app(\App\Services\Hr\CommissionService::class)->forPeriod($rep, $this->period)->commission_amount;

                $bonusAmount = (float) Bonus::where('representative_id', $rep->id)
                    ->whereBetween('bonus_date', [$start, $end])
                    ->sum('amount');

                $outstandingAdvances = Advance::where('representative_id', $rep->id)->where('deducted', false)->get();
                $advanceDeduction = (float) $outstandingAdvances->sum('amount');
                Advance::whereIn('id', $outstandingAdvances->pluck('id'))->update(['deducted' => true]);

                $baseSalary = (float) $rep->salary_base;
                $netSalary = $baseSalary + $commissionAmount + $bonusAmount - $advanceDeduction;

                PayrollEntry::create([
                    'number' => $numbers->generate('payroll_entry'),
                    'representative_id' => $rep->id,
                    'period' => $this->period,
                    'base_salary' => $baseSalary,
                    'commission_amount' => $commissionAmount,
                    'bonus_amount' => $bonusAmount,
                    'advance_deduction' => $advanceDeduction,
                    'other_deduction' => 0,
                    'net_salary' => $netSalary,
                    'status' => 'pending',
                ]);

            }
        });

        $this->redirect(route('payroll.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.payroll.entries.create');
    }
}
