<?php

namespace App\Http\Controllers;

use App\Models\Production\ProductionOrder;
use App\Models\Settings\CompanyProfile;
use App\Services\PdfService;
use App\Services\Production\ProductionService;

/** The batch record (job card) of a production batch. */
class ProductionPdfController extends Controller
{
    public function batch(ProductionOrder $order)
    {
        $order->load(['product.unit', 'factory', 'machine', 'bom', 'rawWarehouse', 'outputWarehouse', 'issues.product.unit', 'issues.batch', 'qcChecks.checker', 'creator']);

        return PdfService::stream('pdfs.production-batch', [
            'order' => $order,
            'materials' => app(ProductionService::class)->materials($order),
            'company' => CompanyProfile::first(),
        ], 'batch-'.$order->batch_no.'.pdf');
    }
}
