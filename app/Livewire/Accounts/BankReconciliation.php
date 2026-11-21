<?php

namespace App\Livewire\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Services\Accounts\AccountReports;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Matching the books with a bank statement: tick each entry that appears on
 * the statement. The balance of the ticked entries should equal the
 * statement balance; what is left unticked is still on its way (cheques not
 * yet presented, deposits not yet credited).
 */
#[Layout('layouts.app')]
#[Title('Bank Reconciliation')]
class BankReconciliation extends Component
{
    #[Url]
    public ?int $account_id = null;

    #[Url]
    public string $statement_date = '';

    public string $statement_balance = '';

    public function mount(): void
    {
        $this->authorize('accounts.view');
        $this->statement_date = $this->statement_date ?: today()->toDateString();
        $this->account_id ??= Account::money()->where('is_bank', true)->orderBy('code')->value('id')
            ?? Account::money()->orderBy('code')->value('id');
    }

    public function toggle(int $lineId): void
    {
        $this->authorize('accounts.manage');
        $line = JournalLine::where('account_id', $this->account_id)->findOrFail($lineId);
        $line->update(['cleared_at' => $line->cleared_at ? null : $this->statement_date]);
    }

    public function render(AccountReports $reports)
    {
        $account = $this->account_id ? Account::money()->find($this->account_id) : null;
        $rows = collect();
        $book = $cleared = 0.0;

        if ($account) {
            $base = $reports->lines(null, $this->statement_date)->where('l.account_id', $account->id);
            $book = (float) (clone $base)->selectRaw('COALESCE(SUM(l.debit) - SUM(l.credit), 0) as v')->value('v');
            $cleared = (float) (clone $base)->whereNotNull('l.cleared_at')->whereDate('l.cleared_at', '<=', $this->statement_date)
                ->selectRaw('COALESCE(SUM(l.debit) - SUM(l.credit), 0) as v')->value('v');

            // Uncleared entries, plus those cleared on this statement (to untick).
            $rows = (clone $base)
                ->where(fn ($q) => $q->whereNull('l.cleared_at')->orWhereDate('l.cleared_at', $this->statement_date))
                ->orderBy('e.entry_date')->orderBy('l.id')
                ->get(['l.id', 'l.debit', 'l.credit', 'l.cleared_at', 'l.narration', 'e.number', 'e.entry_date', 'e.narration as entry_narration', 'e.id as entry_id']);
        }

        $statement = $this->statement_balance !== '' ? (float) $this->statement_balance : null;

        return view('livewire.accounts.bank-reconciliation', [
            'accounts' => Account::money()->orderBy('code')->get(),
            'account' => $account,
            'rows' => $rows,
            'book' => round($book, 2),
            'cleared' => round($cleared, 2),
            'statement' => $statement,
            'difference' => $statement === null ? null : round($statement - $cleared, 2),
        ]);
    }
}
