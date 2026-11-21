<?php

namespace App\Livewire\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Services\Accounts\LedgerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Quick entry for everyday expenses (rent, electricity, transport …): one
 * payment voucher, debit the expense, credit the cash or bank it was paid from.
 */
#[Layout('layouts.app')]
#[Title('Expenses')]
class Expenses extends Component
{
    public string $entry_date = '';
    public ?int $expense_account_id = null;
    public ?int $paid_from_id = null;
    public string $amount = '';
    public string $note = '';

    public function mount(): void
    {
        $this->authorize('accounts.view');
        $this->entry_date = today()->toDateString();
        $this->paid_from_id = Account::money()->where('is_cash', true)->orderBy('code')->value('id');
    }

    public function save(LedgerService $ledger): void
    {
        $this->authorize('accounts.manage');
        $this->validate([
            'entry_date' => 'required|date',
            'expense_account_id' => 'required|exists:accounts,id',
            'paid_from_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|gt:0',
            'note' => 'nullable|string|max:255',
        ], [], ['expense_account_id' => __('expense'), 'paid_from_id' => __('paid from')]);

        $expense = Account::postable()->where('type', 'expense')->find($this->expense_account_id);
        $money = Account::money()->find($this->paid_from_id);
        if (! $expense || ! $money) {
            $this->addError('expense_account_id', __('Choose an expense and a cash or bank account.'));

            return;
        }

        try {
            $ledger->post([
                'type' => 'payment',
                'entry_date' => $this->entry_date,
                'narration' => trim(__($expense->name).($this->note ? ' — '.$this->note : '')),
                'status' => auth()->user()->can('accounts.approve') ? 'posted' : 'draft',
                'manual' => true,
            ], [
                ['account' => (int) $expense->id, 'debit' => (float) $this->amount, 'narration' => $this->note ?: null],
                ['account' => (int) $money->id, 'credit' => (float) $this->amount],
            ]);
        } catch (\RuntimeException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->reset(['expense_account_id', 'amount', 'note']);
        session()->flash('saved', __('Expense recorded.'));
    }

    public function render()
    {
        $expenseIds = Account::postable()->where('type', 'expense')->pluck('id');

        $recent = JournalEntry::where('type', 'payment')
            ->whereHas('lines', fn ($q) => $q->whereIn('account_id', $expenseIds)->where('debit', '>', 0))
            ->with('lines.account')
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->limit(25)->get();

        $monthTotal = (float) app(\App\Services\Accounts\AccountReports::class)
            ->lines(today()->startOfMonth()->toDateString(), today()->toDateString())
            ->whereIn('l.account_id', Account::postable()->where('type', 'expense')->where(fn ($q) => $q->whereNull('system_key')->orWhereNotIn('system_key', ['cogs']))->pluck('id'))
            ->selectRaw('COALESCE(SUM(l.debit) - SUM(l.credit), 0) as v')->value('v');

        return view('livewire.accounts.expenses', [
            'expenseAccounts' => Account::postable()->where('type', 'expense')->where(fn ($q) => $q->whereNull('system_key')->orWhereNotIn('system_key', ['cogs', 'stock_loss']))->orderBy('code')->get(),
            'moneyAccounts' => Account::money()->orderBy('code')->get(),
            'recent' => $recent,
            'monthTotal' => $monthTotal,
        ]);
    }
}
