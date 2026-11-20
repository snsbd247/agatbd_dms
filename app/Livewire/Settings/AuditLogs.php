<?php

namespace App\Livewire\Settings;

use App\Models\Settings\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Audit Log')]
class AuditLogs extends Component
{
    use WithPagination;

    public string $search = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($this->search, fn ($q) => $q->where('action', 'like', "%{$this->search}%"))
            ->latest('id')
            ->paginate(20);

        $summary = [
            'total' => AuditLog::count(),
            'created' => AuditLog::where('action', 'like', '%.created')->count(),
            'updated' => AuditLog::where('action', 'like', '%.updated')->count(),
            'deleted' => AuditLog::where('action', 'like', '%.deleted')->count(),
        ];

        return view('livewire.settings.audit-logs', [
            'logs' => $logs,
            'summary' => $summary,
        ]);
    }
}
