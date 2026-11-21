<?php

namespace App\Http\Controllers;

use App\Models\Delivery\DeliveryInvoice;
use App\Models\Sales\SalesOrder;
use App\Models\Settings\CompanyProfile;
use App\Services\PdfService;

class DeliveryInvoiceController extends Controller
{
    private const RELATIONS = ['items.product', 'items.batch', 'retailer', 'salesOrder.warehouse', 'deliveryNote'];

    public function printInvoice(DeliveryInvoice $deliveryInvoice)
    {
        $invoice = $deliveryInvoice->load(self::RELATIONS);

        $pdf = PdfService::stream('pdfs.delivery-invoice', [
            'invoice' => $invoice,
            'company' => CompanyProfile::first(),
        ], 'delivery-invoice-'.$invoice->number.'.pdf');

        if ($invoice->status !== 'printed') {
            $invoice->update(['status' => 'printed']);
        }

        return $pdf;
    }

    /**
     * Retailer portal: a retailer may view only their own invoices. Viewing
     * here does not change the invoice status.
     */
    public function printForRetailer(DeliveryInvoice $deliveryInvoice)
    {
        $retailer = auth()->user()?->customerAccount();
        abort_unless($retailer && $deliveryInvoice->retailer_id === $retailer->id, 404);

        $invoice = $deliveryInvoice->load(self::RELATIONS);

        return PdfService::stream('pdfs.delivery-invoice', [
            'invoice' => $invoice,
            'company' => CompanyProfile::first(),
        ], 'delivery-invoice-'.$invoice->number.'.pdf');
    }

    public function printSalesOrderInvoices(SalesOrder $salesOrder)
    {
        $invoices = DeliveryInvoice::where('sales_order_id', $salesOrder->id)
            ->with(self::RELATIONS)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            return back()->with('error', __('No invoices found for this sales order.'));
        }

        $pdf = PdfService::stream('pdfs.delivery-invoices-batch', [
            'invoices' => $invoices,
            'company' => CompanyProfile::first(),
        ], 'delivery-invoices-'.$salesOrder->number.'.pdf');

        DeliveryInvoice::whereIn('id', $invoices->pluck('id'))
            ->where('status', '!=', 'printed')
            ->update(['status' => 'printed']);

        return $pdf;
    }
}
