<?php

namespace App\Livewire\Warranty\Claims;

use App\Models\Warranty\WarrantyClaim;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Warranty Claims')]
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

    /** Delete a claim that has not been decided yet. */
    public function delete(int $id): void
    {
        $this->authorize('warranty.manage');
        $c = \App\Models\Warranty\WarrantyClaim::findOrFail($id);
        if ($c->status !== 'pending') {
            session()->flash('error', __('Only a pending warranty claim can be deleted.'));

            return;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($c) {
            $c->tracking()->delete();
            $c->delete();
        });
    }
    public function render()
    {
        $claims = WarrantyClaim::query()
            ->with(['retailer', 'product'])
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
            ->latest('claim_date')
            ->paginate(10);

        $summary = [
            'total' => WarrantyClaim::count(),
            'pending' => WarrantyClaim::where('status', 'pending')->count(),
            'approved' => WarrantyClaim::where('status', 'approved')->count(),
            'resolved' => WarrantyClaim::whereIn('status', ['replaced', 'repaired'])->count(),
        ];

        return view('livewire.warranty.claims.index', compact('claims', 'summary'));
    }
}
