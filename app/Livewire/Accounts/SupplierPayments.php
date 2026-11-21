<?php

namespace App\Livewire\Accounts;

use App\Models\Accounts\Account;
use App\Models\Company;
use App\Services\Accounts\AccountReports;
use App\Services\Accounts\LedgerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What the head office owes each supplier company (from goods received and
 * returned) and paying it: debit Accounts Payable for the company, credit
 * the cash or bank account.
 */
#[Layout('layouts.app')]
#[Title('Supplier Payments')]
class SupplierPayments extends Component
{
    public ?int $company_id = null;
    public ?int $paid_from_id = null;
    public string $amount = '';
    public string $entry_date = '';
    public string $reference = '';

    public function mount(): void
    {
        $this->authorize('accounts.view');
        abort_if(auth()->user()->dealer_id, 403);
        $this->entry_date = today()->toDateString();
        $this->paid_from_id = Account::money()->where('is_bank', true)->orderBy('code')->value('id')
            ?? Account::money()->orderBy('code')->value('id');
    }

    public function pay(int $companyId): void
    {
        $this->company_id = $companyId;
    }

    public function save(LedgerService $ledger): void
    {
        $this->authorize('accounts.manage');
        $this->validate([
            'company_id' => 'required|exists:companies,id',
            'paid_from_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|gt:0',
            'entry_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
        ], [], ['company_id' => __('supplier'), 'paid_from_id' => __('paid from')]);

        $money = Account::money()->find($this->paid_from_id);
        $company = Company::find($this->company_id);

        try {
            $ledger->post([
                'type' => 'payment',
                'entry_date' => $this->entry_date,
                'narration' => trim(__('Payment to :name', ['name' => $company->name]).($this->reference ? ' — '.$this->reference : '')),
                'status' => auth()->user()->can('accounts.approve') ? 'posted' : 'draft',
                'manual' => true,
            ], [
                ['account' => 'ap', 'debit' => (float) $this->amount, 'party_type' => 'company', 'party_id' => $company->id, 'narration' => $this->reference ?: null],
                ['account' => (int) $money->id, 'credit' => (float) $this->amount],
            ]);
        } catch (\RuntimeException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->reset(['company_id', 'amount', 'reference']);
        session()->flash('saved', __('Payment recorded.'));
    }

    public function render(AccountReports $reports)
    {
        $aging = $reports->aging('ap', today()->toDateString());
        $owed = collect($aging['rows'])->where('party_type', 'company')->keyBy('party_id');

        return view('livewire.accounts.supplier-payments', [
            'companies' => Company::orderBy('name')->get(),
            'owed' => $owed,
            'total' => (float) $owed->sum('balance'),
            'moneyAccounts' => Account::money()->orderBy('code')->get(),
            'apAccount' => Account::bySystemKey('ap'),
        ]);
    }
}
