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

    /** Mushak 6.3 (VAT invoice) for a head-office sale, while VAT is switched on. */
    public function mushak(SalesInvoice $salesInvoice)
    {
        $salesInvoice->load(['items.product.unit', 'items.product.brand', 'items.batch', 'retailer', 'salesOrder']);
        abort_unless(\App\Services\Vat\VatService::appliesTo($salesInvoice->retailer), 404);

        // An official Bangla form whatever the user's language.
        $locale = app()->getLocale();
        app()->setLocale('bn');

        try {
            return PdfService::stream('pdfs.mushak-6-3', [
                'invoice' => $salesInvoice,
                'company' => CompanyProfile::first(),
                'bin' => \App\Services\Vat\VatService::bin(),
                'vehicle' => null,
                'signer' => auth()->user(),
                'signerTitle' => __(auth()->user()?->getRoleNames()->first() ?? ''),
            ], 'mushak-6.3-'.$salesInvoice->number.'.pdf', 'landscape');
        } finally {
            app()->setLocale($locale);
        }
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

    /** Mushak 6.7: the credit note for goods a head-office customer sent back. */
    public function creditNote(\App\Models\Returns\ReturnReceive $returnReceive)
    {
        abort_unless(auth()->user()->can('returns.view'), 403);
        $returnReceive->load(['items.product.unit', 'returnRequest.items', 'returnRequest.retailer']);
        $request = $returnReceive->returnRequest;
        $customer = $request?->retailer;
        abort_unless(\App\Services\Vat\VatService::appliesTo($customer), 404);

        $prices = $request?->items->pluck('unit_price', 'product_id') ?? collect();
        $rows = $returnReceive->items->map(function ($item) use ($prices) {
            $rate = (float) ($item->product?->vat_rate ?? 0);
            $total = round((float) $item->quantity * (float) ($prices[$item->product_id] ?? 0), 2);
            $vat = \App\Services\Vat\VatService::included($total, $rate);
            $value = round($total - $vat, 2);

            return [
                'name' => (string) $item->product?->name,
                'unit' => (string) $item->product?->unit?->short_code,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->quantity > 0 ? $value / (float) $item->quantity : 0,
                'value' => $value,
                'rate' => $rate,
                'vat' => $vat,
                'total' => $total,
            ];
        })->all();

        // The customer's earlier invoices for these products (the original 6.3s).
        $invoices = SalesInvoice::where('retailer_id', $customer->id)
            ->whereDate('invoice_date', '<=', $returnReceive->receive_date)
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $returnReceive->items->pluck('product_id')))
            ->latest('invoice_date')->limit(3)->pluck('number')->implode(', ');

        $locale = app()->getLocale();
        app()->setLocale('bn');

        try {
            return PdfService::stream('pdfs.mushak-6-7', [
                'receive' => $returnReceive,
                'request' => $request,
                'customer' => $customer,
                'reason' => $request?->reason,
                'invoices' => $invoices,
                'rows' => $rows,
                'company' => CompanyProfile::first(),
                'bin' => \App\Services\Vat\VatService::bin(),
                'signer' => auth()->user(),
                'signerTitle' => __(auth()->user()?->getRoleNames()->first() ?? ''),
            ], 'mushak-6.7-'.$returnReceive->number.'.pdf', 'landscape');
        } finally {
            app()->setLocale($locale);
        }
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
