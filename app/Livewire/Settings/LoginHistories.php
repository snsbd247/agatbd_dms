<?php

namespace App\Livewire\Settings;

use App\Models\Settings\LoginHistory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Login History')]
class LoginHistories extends Component
{
    use WithPagination;

    public string $filter_status = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['filter_status']);
        $this->resetPage();
    }

    public function render()
    {
        $histories = LoginHistory::query()
            ->with('user')
            ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
            ->latest('login_at')
            ->paginate(20);

        $summary = [
            'total' => LoginHistory::count(),
            'success' => LoginHistory::where('status', 'success')->count(),
            'failed' => LoginHistory::where('status', 'failed')->count(),
            'today' => LoginHistory::where('login_at', '>=', today())->count(),
        ];

        return view('livewire.settings.login-histories', [
            'histories' => $histories,
            'summary' => $summary,
        ]);
    }
}
