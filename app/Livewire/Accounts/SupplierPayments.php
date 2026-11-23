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

    // VAT deducted at source (Mushak 6.6), when VAT is on
    public string $vds_amount = '';
    public string $invoice_no = '';
    public string $invoice_date = '';
    public string $invoice_amount = '';

    /** Id of the 6.6 just issued, for the print link. */
    public ?int $issuedVds = null;

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

    public function save(\App\Services\Vat\VdsService $vds): void
    {
        $this->authorize('accounts.manage');
        $this->validate([
            'company_id' => 'required|exists:companies,id',
            'paid_from_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0',
            'entry_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'vds_amount' => 'nullable|numeric|min:0',
            'invoice_no' => 'nullable|string|max:60',
            'invoice_date' => 'nullable|date',
            'invoice_amount' => 'nullable|numeric|min:0',
        ], [], ['company_id' => __('supplier'), 'paid_from_id' => __('paid from')]);

        $keep = \App\Services\Vat\VatService::enabled() ? round((float) $this->vds_amount, 2) : 0.0;
        if ((float) $this->amount + $keep <= 0) {
            $this->addError('amount', __('Enter the amount paid.'));

            return;
        }
        if ($keep > 0 && blank($this->invoice_no)) {
            $this->addError('invoice_no', __('Give the supplier\'s invoice (6.3) number for the VDS certificate.'));

            return;
        }

        $money = Account::money()->findOrFail($this->paid_from_id);
        $company = Company::findOrFail($this->company_id);

        try {
            $certificate = $vds->paySupplier($company, (int) $money->id, (float) $this->amount, $keep, $this->entry_date, $this->reference ?: null,
                $this->invoice_no ?: null, $this->invoice_date ?: null, (float) ($this->invoice_amount ?: 0),
                auth()->user()->can('accounts.approve') ? 'posted' : 'draft');
        } catch (\RuntimeException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->issuedVds = $certificate?->id;
        $this->reset(['company_id', 'amount', 'reference', 'vds_amount', 'invoice_no', 'invoice_date', 'invoice_amount']);
        session()->flash('saved', $certificate
            ? __('Payment recorded. VDS certificate :n issued.', ['n' => $certificate->number])
            : __('Payment recorded.'));
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
            'vatOn' => \App\Services\Vat\VatService::enabled(),
            'certificates' => \App\Models\Vat\VdsCertificate::with('company')->where('direction', 'issued')->latest('id')->limit(10)->get(),
        ]);
    }
}
