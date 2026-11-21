<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Settings\CompanyProfile;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF version of every report. It runs the report's CSV export with the same
 * filters and lays the rows out as a table, so the PDF always matches the
 * CSV and the report logic lives in one place (ExportController).
 */
class PdfController extends Controller
{
    public const REPORTS = [
        'sales' => 'Sales Report',
        'stock' => 'Stock Report',
        'collections' => 'Collections & Due Report',
        'returns' => 'Return Report',
        'representatives' => 'Representative Performance Report',
        'purchase' => 'Purchase Report',
        'companies' => 'Company Report',
        'products' => 'Product Performance Report',
        'retailers' => 'Retailer Report',
        'warranty' => 'Warranty Report',
        'payroll' => 'Payroll & Commission Report',
        'profit' => 'Profit & Loss Report',
        'expiry' => 'Expiry Report',
        'areas' => 'Area Report',
        'dealers' => 'Dealer Sales Report',
    ];

    /** Columns that are numbers but must not be added up. */
    private const NO_TOTAL = ['%', 'level', 'rate', 'price', 'month', 'year', 'days', 'margin', 'target', 'achievement', 'avg', 'average', 'stock', 'balance', 'limit'];

    public function show(Request $request, string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        // Head office only reports (their pages and CSV are closed to dealer logins too).
        abort_if(in_array($report, ['purchase', 'companies', 'payroll'], true) && $request->user()->dealer_id !== null, 403);

        $response = app(ExportController::class)->{$report}($request);
        abort_unless($response instanceof StreamedResponse, 500);

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        [$headers, $rows] = $this->parse($csv);
        [$numeric, $totals] = $this->analyse($headers, $rows);

        $period = collect([
            $request->query('from') ? __('From').' '.$request->query('from') : null,
            $request->query('to') ? __('To').' '.$request->query('to') : null,
            $request->query('month') ? __('Month').' '.$request->query('month') : null,
            $request->query('period') ? __('Period').' '.$request->query('period') : null,
        ])->filter()->implode(' · ');

        $title = __(self::REPORTS[$report]);

        return PdfService::stream('pdfs.report', [
            'title' => $title,
            'period' => $period,
            'headers' => $headers,
            'rows' => $rows,
            'numeric' => $numeric,
            'totals' => $totals,
            'company' => CompanyProfile::first(),
        ], str_replace(' ', '-', strtolower(self::REPORTS[$report])).'-'.today()->toDateString().'.pdf', count($headers) > 6 ? 'landscape' : 'portrait');
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, string>>} */
    private function parse(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);

        $headers = fgetcsv($stream, escape: '\\') ?: [];
        $rows = [];
        while (($row = fgetcsv($stream, escape: '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = array_pad($row, count($headers), '');
        }
        fclose($stream);

        return [$headers, $rows];
    }

    /**
     * Which columns are numeric (right-aligned, formatted), and the column
     * totals for the ones it makes sense to add up.
     */
    private function analyse(array $headers, array $rows): array
    {
        $numeric = $totals = [];

        foreach ($headers as $i => $header) {
            $values = array_filter(array_column($rows, $i), fn ($v) => $v !== '' && $v !== null);
            $isNumeric = $values !== [] && collect($values)->every(fn ($v) => is_numeric($v));
            $numeric[$i] = $isNumeric;

            $h = strtolower($header);
            $skip = collect(self::NO_TOTAL)->contains(fn ($w) => str_contains($h, $w))
                || str_contains($h, 'id') && strlen($h) <= 3
                || preg_match('/\bno\b|#|number|code|phone|qty on hand/', $h);

            if ($isNumeric && ! $skip && count($rows) > 1) {
                $totals[$i] = array_sum(array_map('floatval', $values));
            }
        }

        return [$numeric, $totals];
    }
}
