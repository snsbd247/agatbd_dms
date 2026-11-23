<?php

namespace App\Livewire\Reports;

use App\Models\Sales\SalesInvoice;
use App\Models\Vat\VatReturn;
use App\Services\Vat\VatReturnService;
use App\Services\Vat\VatService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monthly VAT return (Mushak 9.1) of the head office: output VAT on sales,
 * input VAT on purchases and imports, the set-off and what is left to pay,
 * with the sales (6.2) and purchase (6.1) registers. The month can be
 * settled here, which posts the voucher.
 */
#[Layout('layouts.app')]
#[Title('VAT Report')]
class Vat extends Component
{
    #[Url]
    public string $month = '';

    #[Url]
    public string $tab = 'sales';

    public string $paid_from = 'bank';
    public string $paid_on = '';
    public string $reference = '';

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : today()->format('Y-m');
        $this->tab = in_array($this->tab, ['sales', 'purchases'], true) ? $this->tab : 'sales';
        $this->paid_on = today()->toDateString();
    }

    private function monthValue(): string
    {
        return preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : today()->format('Y-m');
    }

    public function settle(VatReturnService $service): void
    {
        $this->authorize('accounts.manage');
        $this->validate([
            'paid_from' => 'required|in:bank,cash',
            'paid_on' => 'required|date',
            'reference' => 'nullable|string|max:100',
        ]);

        try {
            $service->settle($this->monthValue(), $this->paid_from, $this->paid_on, $this->reference ?: null);
        } catch (\RuntimeException $e) {
            $this->addError('paid_on', $e->getMessage());

            return;
        }
        $this->reset('reference');
        session()->flash('success', __('VAT for :m settled and posted.', ['m' => $this->monthValue()]));
    }

    public function undo(VatReturnService $service): void
    {
        $this->authorize('accounts.manage');
        $return = VatReturn::where('period', $this->monthValue())->firstOrFail();

        try {
            $service->undo($return);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        session()->flash('success', __('Settlement undone; its voucher was reversed.'));
    }

    public function render(VatReturnService $service)
    {
        $month = $this->monthValue();
        [$start, $end] = VatReturnService::bounds($month);

        $invoices = SalesInvoice::query()
            ->with('retailer')
            ->whereHas('retailer', fn ($q) => $q->whereNull('dealer_id'))
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('invoice_date')->orderBy('id')
            ->get();

        $salesVat = round((float) $invoices->sum('vat_amount'), 2);

        return view('livewire.reports.vat', [
            'enabled' => VatService::enabled(),
            'bin' => VatService::bin(),
            'invoices' => $invoices,
            'purchases' => $this->tab === 'purchases' ? $service->purchaseRegister($month) : [],
            'sales' => [
                'value' => round((float) $invoices->sum('grand_total') - $salesVat, 2),
                'vat' => $salesVat,
            ],
            's' => $service->summary($month),
            'settled' => VatReturn::with('journalEntry', 'settler')->where('period', $month)->first(),
            'monthEnded' => $end->lt(now()),
        ]);
    }
}
