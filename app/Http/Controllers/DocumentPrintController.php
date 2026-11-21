<?php

namespace App\Http\Controllers;

use App\Models\PaymentCollection;
use App\Models\RetailerLedger;
use App\Models\Sales\SalesInvoice;
use App\Models\Settings\CompanyProfile;
use App\Services\PdfService;

/**
 * Sales Invoice and Money Receipt PDFs, for staff and for the retailer
 * portal (where a retailer can open only their own documents).
 */
class DocumentPrintController extends Controller
{
    public function salesInvoice(SalesInvoice $salesInvoice)
    {
        return $this->renderSalesInvoice($salesInvoice);
    }

    public function salesInvoiceForRetailer(SalesInvoice $salesInvoice)
    {
        $this->abortUnlessOwnRetailer($salesInvoice->retailer_id);

        return $this->renderSalesInvoice($salesInvoice);
    }

    public function moneyReceipt(PaymentCollection $collection)
    {
        return $this->renderReceipt($collection);
    }

    public function moneyReceiptForRetailer(PaymentCollection $collection)
    {
        $this->abortUnlessOwnRetailer($collection->retailer_id);

        return $this->renderReceipt($collection);
    }

    private function abortUnlessOwnRetailer(?int $retailerId): void
    {
        $retailer = auth()->user()?->customerAccount();
        abort_unless($retailer && $retailerId === $retailer->id, 404);
    }

    private function renderSalesInvoice(SalesInvoice $invoice)
    {
        $invoice->load(['items.product.unit', 'items.batch', 'retailer', 'salesOrder.representative']);

        return PdfService::stream('pdfs.sales-invoice', [
            'invoice' => $invoice,
            'company' => CompanyProfile::first(),
        ], 'sales-invoice-'.$invoice->number.'.pdf');
    }

    private function renderReceipt(PaymentCollection $collection)
    {
        $collection->load(['retailer', 'collectedBy']);

        $balanceAfter = RetailerLedger::where('reference_type', $collection->getMorphClass())
            ->where('reference_id', $collection->id)
            ->value('balance_after');

        return PdfService::stream('pdfs.money-receipt', [
            'collection' => $collection,
            'balanceAfter' => $balanceAfter === null ? null : (float) $balanceAfter,
            'company' => CompanyProfile::first(),
        ], 'money-receipt-'.$collection->number.'.pdf');
    }
}
