<?php

namespace App\Livewire\Returns\Requests;

use App\Models\Returns\ReturnRequest;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Return Requests')]
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
        $requests = ReturnRequest::query()
            ->with('retailer')
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
            ->latest('request_date')
            ->paginate(10);

        $summary = [
            'total' => ReturnRequest::count(),
            'pending' => ReturnRequest::where('status', 'pending')->count(),
            'approved' => ReturnRequest::where('status', 'approved')->count(),
            'received' => ReturnRequest::where('status', 'received')->count(),
        ];

        return view('livewire.returns.requests.index', compact('requests', 'summary'));
    }
}
