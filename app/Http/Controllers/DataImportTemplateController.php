<?php

namespace App\Http\Controllers;

use App\Services\DataImportService;

/** A CSV template (header + one example row) for each data import. */
class DataImportTemplateController extends Controller
{
    public function __invoke(string $type, DataImportService $service)
    {
        abort_unless(isset(DataImportService::TYPES[$type]), 404);
        abort_unless(auth()->user()->can('settings.manage'), 403);

        return response()->streamDownload(function () use ($service, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // so Excel opens Bangla text correctly
            foreach ($service->template($type) as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, 'import-'.str_replace('_', '-', $type).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
