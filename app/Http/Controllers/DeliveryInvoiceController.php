<?php

namespace App\Http\Controllers;

use App\Models\Delivery\DeliveryInvoice;
use App\Models\Sales\SalesOrder;
use App\Models\Settings\CompanyProfile;
use Barryvdh\DomPDF\Facade\Pdf;

class DeliveryInvoiceController extends Controller
{
    private const RELATIONS = ['items.product', 'items.batch', 'retailer', 'salesOrder.warehouse', 'deliveryNote'];

    public function printInvoice(DeliveryInvoice $deliveryInvoice)
    {
        $invoice = $deliveryInvoice->load(self::RELATIONS);

        $pdf = Pdf::loadView('pdfs.delivery-invoice', [
            'invoice' => $invoice,
            'company' => CompanyProfile::first(),
        ])->setPaper('A4', 'portrait');

        if ($invoice->status !== 'printed') {
            $invoice->update(['status' => 'printed']);
        }

        return $pdf->stream('delivery-invoice-'.$invoice->number.'.pdf');
    }

    /**
     * Retailer portal: a retailer may view only their own invoices. Viewing
     * here does not change the invoice status.
     */
    public function printForRetailer(DeliveryInvoice $deliveryInvoice)
    {
        $retailer = auth()->user()?->retailer;
        abort_unless($retailer && $deliveryInvoice->retailer_id === $retailer->id, 404);

        $invoice = $deliveryInvoice->load(self::RELATIONS);

        return Pdf::loadView('pdfs.delivery-invoice', [
            'invoice' => $invoice,
            'company' => CompanyProfile::first(),
        ])->setPaper('A4', 'portrait')->stream('delivery-invoice-'.$invoice->number.'.pdf');
    }

    public function printSalesOrderInvoices(SalesOrder $salesOrder)
    {
        $invoices = DeliveryInvoice::where('sales_order_id', $salesOrder->id)
            ->with(self::RELATIONS)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            return back()->with('error', 'No invoices found for this sales order.');
        }

        $pdf = Pdf::loadView('pdfs.delivery-invoices-batch', [
            'invoices' => $invoices,
            'company' => CompanyProfile::first(),
        ])->setPaper('A4', 'portrait');

        DeliveryInvoice::whereIn('id', $invoices->pluck('id'))
            ->where('status', '!=', 'printed')
            ->update(['status' => 'printed']);

        return $pdf->stream('delivery-invoices-'.$salesOrder->number.'.pdf');
    }
}
