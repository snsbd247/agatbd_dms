<?php

namespace App\Livewire\Accounts\Vouchers;

use App\Models\Accounts\JournalEntry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** All vouchers of the business: entered by hand and automatic (the day book). */
#[Layout('layouts.app')]
#[Title('Vouchers')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('accounts.view');
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $base = JournalEntry::query()
            ->whereBetween('entry_date', [$this->from, $this->to])
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->search}%")->orWhere('narration', 'like', "%{$this->search}%")));

        return view('livewire.accounts.vouchers.index', [
            'entries' => (clone $base)->with('lines')->orderByDesc('entry_date')->orderByDesc('id')->paginate(20),
            'drafts' => JournalEntry::where('status', 'draft')->count(),
        ]);
    }
}
