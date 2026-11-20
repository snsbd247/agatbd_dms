<?php

namespace App\Livewire\Payroll\Entries;

use App\Models\Payroll\PayrollEntry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Payroll')]
class Index extends Component
{
    use WithPagination;

    public string $period = '';
    public string $filter_status = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['period', 'filter_status']);
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->filter_status = $status;
        $this->resetPage();
    }

    public function render()
    {
        $entries = PayrollEntry::query()
            ->with('representative')
            ->when($this->period, fn ($q) => $q->where('period', $this->period))
            ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
            ->latest('period')
            ->paginate(10);

        $summary = [
            'total' => PayrollEntry::count(),
            'pending' => PayrollEntry::where('status', 'pending')->count(),
            'paid' => PayrollEntry::where('status', 'paid')->count(),
            'total_net_salary' => PayrollEntry::sum('net_salary'),
        ];

        return view('livewire.payroll.entries.index', compact('entries', 'summary'));
    }
}
