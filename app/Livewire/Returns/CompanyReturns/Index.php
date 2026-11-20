<?php

namespace App\Livewire\Returns\CompanyReturns;

use App\Models\Returns\CompanyReturn;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Company Returns')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter_status = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filter_status']);
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->filter_status = $status;
        $this->resetPage();
    }

    public function render()
    {
        $companyReturns = CompanyReturn::query()
            ->with('company')
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
            ->latest('return_date')
            ->paginate(10);

        $summary = [
            'total' => CompanyReturn::count(),
            'draft' => CompanyReturn::where('status', 'draft')->count(),
            'sent' => CompanyReturn::where('status', 'sent')->count(),
            'completed' => CompanyReturn::where('status', 'completed')->count(),
        ];

        return view('livewire.returns.company-returns.index', compact('companyReturns', 'summary'));
    }
}
