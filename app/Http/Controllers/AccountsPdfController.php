<?php

namespace App\Http\Controllers;

use App\Models\Accounts\JournalEntry;
use App\Models\Settings\CompanyProfile;
use App\Services\Accounts\ReportData;
use App\Services\PdfService;
use Illuminate\Http\Request;

/** PDFs of the accounting reports and of a voucher. */
class AccountsPdfController extends Controller
{
    public function report(Request $request, string $report, ReportData $data)
    {
        abort_unless($request->user()->can('accounts.view') && isset(ReportData::REPORTS[$report]), 404);

        $p = ReportData::defaults($request->query());
        $built = $data->build($report, $request->query());
        $pointInTime = in_array($report, ['balance-sheet', 'receivables', 'payables'], true);

        return PdfService::stream('pdfs.accounts-report', [
            'title' => __(ReportData::REPORTS[$report]),
            'subtitle' => $report === 'ledger' && $built['account'] ? $built['account']->code.' · '.__($built['account']->name) : null,
            'period' => $pointInTime
                ? __('As on :d', ['d' => \Carbon\Carbon::parse($p['to'])->format('d M Y')])
                : \Carbon\Carbon::parse($p['from'])->format('d M Y').' – '.\Carbon\Carbon::parse($p['to'])->format('d M Y'),
            'table' => in_array($report, ['receivables', 'payables'], true) ? 'aging' : $report,
            'data' => $built,
            'partyFixed' => (bool) $p['party_id'],
            'company' => CompanyProfile::first(),
        ], $report.'-'.$p['to'].'.pdf', $report === 'trial-balance' || $report === 'ledger' ? 'landscape' : 'portrait');
    }

    public function voucher(JournalEntry $entry)
    {
        abort_unless(request()->user()->can('accounts.view'), 403);
        $entry->load(['lines.account', 'creator', 'approver']);

        return PdfService::stream('pdfs.voucher', [
            'entry' => $entry,
            'company' => CompanyProfile::first(),
        ], $entry->number.'.pdf');
    }
}
