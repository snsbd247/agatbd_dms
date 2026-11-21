<?php

namespace App\Livewire\Accounts;

use App\Livewire\Accounts\Concerns\PicksParties;
use App\Models\Accounts\Account;
use App\Services\Accounts\ReportData;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** The accounting reports (one page, the report chosen by the URL). */
#[Layout('layouts.app')]
class Reports extends Component
{
    use PicksParties;

    public string $report = 'trial-balance';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $account = '';

    #[Url]
    public string $party_type = '';

    #[Url]
    public string $party_id = '';

    public function mount(string $report): void
    {
        $this->authorize('accounts.view');
        abort_unless(isset(ReportData::REPORTS[$report]), 404);
        $this->report = $report;

        $d = ReportData::defaults(['from' => $this->from, 'to' => $this->to]);
        $this->from = $d['from'];
        $this->to = $d['to'];
    }

    public function updatedAccount(): void
    {
        $this->party_type = '';
        $this->party_id = '';
    }

    public function render(ReportData $data)
    {
        $params = ['from' => $this->from, 'to' => $this->to, 'account' => $this->account, 'party_type' => $this->party_type, 'party_id' => $this->party_id];
        $chosen = $this->account !== '' ? Account::find((int) $this->account) : null;
        $partyType = $this->report === 'ledger' ? $this->partyTypeFor($chosen) : null;

        return view('livewire.accounts.reports', [
            'data' => $data->build($this->report, $params),
            'title' => __(ReportData::REPORTS[$this->report]),
            'accounts' => $this->report === 'ledger' ? Account::postable()->orderBy('code')->get() : collect(),
            'ledgerPartyType' => $partyType,
            'parties' => $partyType && $partyType !== 'head_office' ? $this->partyOptions()[$partyType] : [],
            'pdfUrl' => route('accounts.reports.pdf', ['report' => $this->report] + array_filter($params, fn ($v) => $v !== '' && $v !== null)),
        ])->title(__(ReportData::REPORTS[$this->report]));
    }
}
