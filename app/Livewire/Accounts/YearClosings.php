<?php

namespace App\Livewire\Accounts;

use App\Models\Accounts\FiscalYearClosing;
use App\Models\Accounts\JournalEntry;
use App\Services\Accounts\YearClosing;
use App\Support\FiscalYear;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Fiscal years of the business: close an ended year, or reopen the last closed one. */
#[Layout('layouts.app')]
#[Title('Year Closing')]
class YearClosings extends Component
{
    public function mount(): void
    {
        $this->authorize('accounts.view');
    }

    public function close(string $start): void
    {
        $this->authorize('accounts.approve');
        try {
            app(YearClosing::class)->close($start);
            session()->flash('saved', __('Fiscal year closed.'));
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
        }
    }

    public function reopen(int $id): void
    {
        $this->authorize('accounts.approve');
        try {
            app(YearClosing::class)->reopen(FiscalYearClosing::findOrFail($id));
            session()->flash('saved', __('Fiscal year reopened.'));
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
        }
    }

    public function render()
    {
        $first = JournalEntry::where('status', 'posted')->min('entry_date');
        $years = [];

        if ($first) {
            $fy = FiscalYear::for($first);
            $current = FiscalYear::current();
            $closings = FiscalYearClosing::with('entry')->get()->keyBy('label');

            while ($fy['start']->lte($current['start'])) {
                $years[] = $fy + ['closing' => $closings[$fy['label']] ?? null, 'ended' => $fy['end']->isPast()];
                $fy = FiscalYear::for($fy['end']->addDay());
            }
        }

        return view('livewire.accounts.year-closings', ['years' => array_reverse($years)]);
    }
}
