<?php

namespace App\Livewire\Reports;

use App\Models\Payroll\PayrollEntry;
use App\Models\Representative;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Payroll & Commission Report')]
class PayrollSummary extends Component
{
    use WithPagination;

    #[Url]
    public string $from_period = '';

    #[Url]
    public string $to_period = '';

    #[Url]
    public string $representative_id = '';

    public function mount(): void
    {
        $this->from_period = $this->from_period ?: today()->startOfMonth()->format('Y-m');
        $this->to_period = $this->to_period ?: today()->format('Y-m');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    private function baseQuery()
    {
        return PayrollEntry::query()
            ->whereBetween('period', [$this->from_period, $this->to_period])
            ->when($this->representative_id, fn ($q) => $q->where('representative_id', $this->representative_id));
    }

    public function render()
    {
        $entries = $this->baseQuery()
            ->with('representative')
            ->latest('period')
            ->latest('id')
            ->paginate(15);

        $summaryQuery = $this->baseQuery();
        $summary = [
            'total_net_salary' => (float) (clone $summaryQuery)->sum('net_salary'),
            'total_commission' => (float) (clone $summaryQuery)->sum('commission_amount'),
            'total_advances' => (float) (clone $summaryQuery)->sum('advance_deduction'),
        ];

        return view('livewire.reports.payroll-summary', [
            'entries' => $entries,
            'summary' => $summary,
            'representatives' => Representative::orderBy('name')->get(),
        ]);
    }
}
