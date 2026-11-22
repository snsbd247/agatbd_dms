<?php

namespace App\Livewire\Reports;

use App\Models\Accounts\Account;
use App\Models\Sales\SalesInvoice;
use App\Services\Accounts\AccountReports;
use App\Services\Vat\VatService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monthly VAT summary for the return (Mushak 9.1): head-office sales with the
 * VAT inside them, VAT given back on returns, and the Output VAT account.
 */
#[Layout('layouts.app')]
#[Title('VAT Report')]
class Vat extends Component
{
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : today()->format('Y-m');
    }

    public function render(AccountReports $reports)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : today()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $invoices = SalesInvoice::query()
            ->with('retailer')
            ->whereHas('retailer', fn ($q) => $q->whereNull('dealer_id'))
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('invoice_date')->orderBy('id')
            ->get();

        $salesVat = round((float) $invoices->sum('vat_amount'), 2);
        $returnsVat = $credits = $debits = $closing = 0.0;

        if ($account = Account::bySystemKey('vat_output')) {
            $lines = $reports->lines($start->toDateString(), $end->toDateString())->where('l.account_id', $account->id);
            $returnsVat = (float) (clone $lines)->where('e.source_event', 'sales_return')->sum('l.debit');
            $credits = (float) (clone $lines)->sum('l.credit');
            $debits = (float) (clone $lines)->sum('l.debit');
            $t = $reports->totals(null, $end->toDateString())[$account->id] ?? null;
            $closing = round((float) ($t->credit ?? 0) - (float) ($t->debit ?? 0), 2);
        }

        return view('livewire.reports.vat', [
            'enabled' => VatService::enabled(),
            'bin' => VatService::bin(),
            'invoices' => $invoices,
            'summary' => [
                'value' => round((float) $invoices->sum('grand_total') - $salesVat, 2),
                'sales_vat' => $salesVat,
                'returns_vat' => round($returnsVat, 2),
                'net' => round($salesVat - $returnsVat, 2),
                // payments to the government and other adjustments
                'other' => round(($debits - $returnsVat) - ($credits - $salesVat), 2),
                'closing' => $closing,
            ],
        ]);
    }
}
