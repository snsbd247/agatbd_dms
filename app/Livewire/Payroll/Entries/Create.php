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

        $representatives = Representative::where('active', true)
            ->whereNotIn('id', $existingRepIds)
            ->get();

        if ($representatives->isEmpty()) {
            $this->addError('period', 'Every active representative already has a payroll entry for this period.');

            return;
        }

        DB::transaction(function () use ($representatives, $start, $end, $numbers) {
            foreach ($representatives as $rep) {
                $deliveredAmount = (float) SalesInvoice::whereHas('salesOrder', fn ($q) => $q->where('representative_id', $rep->id))
                    ->whereBetween('invoice_date', [$start, $end])
                    ->sum('grand_total');

                $retailerIds = Retailer::where('representative_id', $rep->id)->pluck('id');

                $returnAdjustment = (float) ReturnRequestItem::whereHas('returnRequest', fn ($q) => $q->where('status', 'received')
                    ->whereIn('retailer_id', $retailerIds)
                    ->whereBetween('request_date', [$start, $end]))
                    ->get()
                    ->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);

                $commissionBase = max(0, $deliveredAmount - $returnAdjustment);
                $commissionAmount = round($commissionBase * (float) $rep->commission_rate / 100, 2);

                Commission::create([
                    'representative_id' => $rep->id,
                    'sales_invoice_id' => null,
                    'period' => $this->period,
                    'delivered_amount' => $deliveredAmount,
                    'commission_rate' => $rep->commission_rate,
                    'commission_amount' => $commissionAmount,
                    'return_adjustment' => $returnAdjustment,
                ]);

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

                RepresentativeTarget::updateOrCreate(
                    ['representative_id' => $rep->id, 'period' => $this->period],
                    ['target_amount' => $rep->target_amount, 'achieved_amount' => $deliveredAmount]
                );
            }
        });

        $this->redirect(route('payroll.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.payroll.entries.create');
    }
}
