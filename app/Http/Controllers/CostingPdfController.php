<?php

namespace App\Http\Controllers;

use App\Models\Costing\CostSheet;
use App\Models\Settings\CompanyProfile;
use App\Services\PdfService;
use App\Services\Vat\VatService;

/** Mushak 4.3 (made goods) or a plain cost sheet (bought / imported goods), always in Bangla. */
class CostingPdfController extends Controller
{
    public function sheet(CostSheet $sheet)
    {
        abort_unless(auth()->user()?->can('costing.view'), 403);   // the route checks it too
        $sheet->load(['items.product.unit', 'product.unit', 'product.brand', 'approver']);

        $locale = app()->getLocale();
        app()->setLocale('bn');

        try {
            return PdfService::stream('pdfs.mushak-4-3', [
                'sheet' => $sheet,
                'mushak' => in_array($sheet->basis, ['formula', 'kit'], true),
                'company' => CompanyProfile::first(),
                'bin' => \App\Models\Settings\AppSetting::get(VatService::BIN),
                'signerTitle' => __($sheet->approver?->getRoleNames()->first() ?? auth()->user()?->getRoleNames()->first() ?? ''),
            ], 'cost-sheet-'.$sheet->number.'.pdf', 'landscape');
        } finally {
            app()->setLocale($locale);
        }
    }
}
