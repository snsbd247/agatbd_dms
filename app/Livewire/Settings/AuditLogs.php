<?php

namespace App\Livewire\Settings;

use App\Models\Settings\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
    public string $module = '';
    public string $event = '';
    public string $userId = '';
    public string $from = '';
    public string $to = '';

    public ?int $expanded = null;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'module', 'event', 'userId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'module', 'event', 'userId', 'from', 'to']);
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($this->module, fn ($q) => $q->where('action', 'like', $this->module.'.%'))
            ->when($this->event, fn ($q) => $q->where('action', 'like', '%.'.$this->event))
            ->when($this->userId === 'system', fn ($q) => $q->whereNull('user_id'))
            ->when($this->userId !== '' && $this->userId !== 'system', fn ($q) => $q->where('user_id', $this->userId))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('action', 'like', "%{$this->search}%")
                ->orWhere('new_values', 'like', "%{$this->search}%")
                ->orWhere('old_values', 'like', "%{$this->search}%")))
            ->latest('id')
            ->paginate(25);

        $modules = AuditLog::query()
            ->select(DB::raw("substr(action, 1, instr(action, '.') - 1) as module"))
            ->distinct()
            ->pluck('module')
            ->filter()
            ->sort()
            ->values();

        $summary = [
            'total' => AuditLog::count(),
            'today' => AuditLog::whereDate('created_at', today())->count(),
            'updated' => AuditLog::where('action', 'like', '%.updated')->count(),
            'deleted' => AuditLog::where('action', 'like', '%.deleted')->count(),
        ];

        return view('livewire.settings.audit-logs', [
            'logs' => $logs,
            'summary' => $summary,
            'modules' => $modules,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
