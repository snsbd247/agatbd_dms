<?php

namespace App\Livewire\Reports;

use App\Models\Warranty\WarrantyClaim;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Warranty Report')]
class Warranty extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    private function baseQuery()
    {
        return WarrantyClaim::query()
            ->whereBetween('claim_date', [Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay()])
            ->when($this->status, fn ($q) => $q->where('status', $this->status));
    }

    public function render()
    {
        $claims = $this->baseQuery()
            ->with(['retailer', 'product'])
            ->latest('claim_date')
            ->latest('id')
            ->paginate(15);

        $summaryQuery = $this->baseQuery();
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'pending' => (clone $summaryQuery)->where('status', 'pending')->count(),
            'resolved' => (clone $summaryQuery)->whereIn('status', ['replaced', 'repaired'])->count(),
        ];

        return view('livewire.reports.warranty', [
            'claims' => $claims,
            'summary' => $summary,
        ]);
    }
}
