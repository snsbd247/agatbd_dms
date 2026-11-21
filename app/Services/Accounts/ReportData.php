<?php

namespace App\Services\Accounts;

use App\Models\Accounts\Account;
use App\Support\FiscalYear;

/**
 * Builds each accounting report from its parameters, for the report page and
 * its PDF alike (so the two always show the same figures).
 */
class ReportData
{
    public const REPORTS = [
        'ledger' => 'General Ledger',
        'trial-balance' => 'Trial Balance',
        'profit-loss' => 'Profit & Loss Account',
        'balance-sheet' => 'Balance Sheet',
        'cash-flow' => 'Cash Flow Statement',
        'receivables' => 'Receivables (Customers)',
        'payables' => 'Payables',
    ];

    public function __construct(private AccountReports $reports) {}

    /** Defaults: the current fiscal year to date. */
    public static function defaults(array $params): array
    {
        $fy = FiscalYear::current();

        return [
            'from' => ($params['from'] ?? '') ?: $fy['start']->toDateString(),
            'to' => ($params['to'] ?? '') ?: today()->toDateString(),
            'account' => isset($params['account']) && $params['account'] !== '' ? (int) $params['account'] : null,
            'party_type' => ($params['party_type'] ?? '') ?: null,
            'party_id' => isset($params['party_id']) && $params['party_id'] !== '' ? (int) $params['party_id'] : null,
        ];
    }

    public function build(string $report, array $params): array
    {
        $p = self::defaults($params);

        return match ($report) {
            'ledger' => $this->ledger($p),
            'trial-balance' => $this->reports->trialBalance($p['from'], $p['to']),
            'profit-loss' => $this->reports->profitLoss($p['from'], $p['to']),
            'balance-sheet' => $this->reports->balanceSheet($p['to']),
            'cash-flow' => $this->reports->cashFlow($p['from'], $p['to']),
            'receivables' => $this->reports->aging('ar', $p['to']),
            'payables' => $this->reports->aging('ap', $p['to']),
            default => abort(404),
        };
    }

    private function ledger(array $p): array
    {
        $account = $p['account'] ? Account::postable()->find($p['account']) : null;

        return ['account' => $account] + ($account
            ? $this->reports->ledger($account, $p['from'], $p['to'], $p['party_type'], $p['party_id'])
            : ['opening' => 0, 'rows' => [], 'closing' => 0, 'debit' => 0, 'credit' => 0]);
    }
}
