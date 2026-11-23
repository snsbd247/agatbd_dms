<?php

namespace App\Http\Controllers;

use App\Services\PdfService;

/**
 * Print (PDF) for documents that have no dedicated layout: sales and
 * purchase orders, goods receipts, delivery challans, returns, transfers,
 * damage reports and warranty claims. Each builder fills the generic
 * pdfs.document view. Records are looked up through their models, so the
 * dealer (tenant) scope applies: another business's record is a 404.
 */
class DocumentPdfController extends Controller
{
    /** type => [model, permission, builder method] */
    private const TYPES = [
        'sales-order' => [\App\Models\Sales\SalesOrder::class, 'sales.view', 'salesOrder'],
        'purchase-order' => [\App\Models\Purchase\PurchaseOrder::class, 'purchase.view', 'purchaseOrder'],
        'goods-receipt' => [\App\Models\Purchase\GoodsReceipt::class, 'purchase.view', 'goodsReceipt'],
        'delivery-note' => [\App\Models\Delivery\DeliveryNote::class, 'delivery.view', 'deliveryNote'],
        'return-request' => [\App\Models\Returns\ReturnRequest::class, 'returns.view', 'returnRequest'],
        'purchase-return' => [\App\Models\Purchase\PurchaseReturn::class, 'purchase.view', 'purchaseReturn'],
        'company-return' => [\App\Models\Returns\CompanyReturn::class, 'returns.view', 'companyReturn'],
        'stock-transfer' => [\App\Models\Production\StockTransfer::class, 'inventory.view', 'stockTransfer'],
        'damage' => [\App\Models\Damage\DamageRecord::class, 'damage.view', 'damage'],
        'warranty-claim' => [\App\Models\Warranty\WarrantyClaim::class, 'warranty.view', 'warrantyClaim'],
    ];

    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    public function __invoke(string $type, int $id)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$model, $permission, $builder] = self::TYPES[$type];
        abort_unless(auth()->user()->can($permission), 403);

        $record = $model::findOrFail($id);
        $doc = $this->{$builder}($record);

        return PdfService::stream('pdfs.document', ['doc' => $doc, 'company' => \App\Models\Settings\CompanyProfile::first()], $doc['number'].'.pdf');
    }

    // ------------------------------------------------------------ helpers

    private function q($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
    }

    private function m($n): string
    {
        return number_format((float) $n, 2);
    }

    private function d($date): string
    {
        return $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '';
    }

    private function status(?string $s): string
    {
        return $s ? __(ucfirst(str_replace('_', ' ', $s))) : '';
    }

    private function unit($product): string
    {
        return (string) ($product?->unit?->short_code ?? '');
    }

    private function companyParty($company, string $title): array
    {
        return [$title, [$company?->name, $company?->address, $company?->phone]];
    }

    private function retailerParty($retailer, string $title): array
    {
        return [$title, [$retailer?->name, $retailer?->shop_name, $retailer?->address, $retailer?->phone, $retailer?->code ? __('Code').': '.$retailer->code : null]];
    }

    private function warehouseParty($warehouse, string $title): array
    {
        return [$title, [$warehouse?->name, $warehouse?->address]];
    }

    // ----------------------------------------------------------- builders

    private function salesOrder($o): array
    {
        $o->load('retailer', 'warehouse', 'representative', 'items.product.unit');
        $vat = (float) ($o->vat_amount ?? 0);

        return [
            'title' => __('Sales Order'),
            'number' => $o->number,
            'date' => $this->d($o->order_date),
            'status' => $this->status($o->status),
            'meta' => [$o->representative ? __('Representative').': '.$o->representative->name : null],
            'parties' => [$this->retailerParty($o->retailer, __('Customer')), $this->warehouseParty($o->warehouse, __('From warehouse'))],
            'columns' => [[__('Product'), 'l', null], [__('Quantity'), 'r', 12], [__('Unit'), 'c', 8], [__('Unit Price'), 'r', 14], [__('Amount'), 'r', 16]],
            'rows' => $o->items->map(fn ($i) => [$i->product?->name.' ('.$i->product?->sku.')', $this->q($i->quantity), $this->unit($i->product), $this->m($i->unit_price), $this->m($i->quantity * $i->unit_price)])->all(),
            'totals' => array_values(array_filter([
                [__('Subtotal'), $this->m($o->subtotal)],
                (float) $o->discount > 0 ? [__('Discount'), '- '.$this->m($o->discount)] : null,
                [__('Grand Total'), $this->m($o->grand_total), true],
            ])),
            'words' => (float) $o->grand_total,
            'signatures' => [__('Customer'), __('Prepared by'), __('Approved by').($o->approvedBy ? ': '.$o->approvedBy->name : '')],
        ];
    }

    private function purchaseOrder($o): array
    {
        $o->load('company', 'warehouse', 'createdBy', 'items.product.unit');

        return [
            'title' => __('Purchase Order'),
            'number' => $o->number,
            'date' => $this->d($o->order_date),
            'status' => $this->status($o->status),
            'meta' => [$o->expected_date ? __('Expected').': '.$this->d($o->expected_date) : null],
            'parties' => [$this->companyParty($o->company, __('Supplier')), $this->warehouseParty($o->warehouse, __('Deliver to'))],
            'columns' => [[__('Product'), 'l', null], [__('Quantity'), 'r', 12], [__('Unit'), 'c', 8], [__('Received'), 'r', 12], [__('Unit Price'), 'r', 14], [__('Amount'), 'r', 16]],
            'rows' => $o->items->map(fn ($i) => [$i->product?->name.' ('.$i->product?->sku.')', $this->q($i->quantity), $this->unit($i->product), $this->q($i->received_quantity), $this->m($i->unit_price), $this->m($i->quantity * $i->unit_price)])->all(),
            'totals' => [[__('Total'), $this->m($o->total_amount), true]],
            'words' => (float) $o->total_amount,
            'signatures' => [__('Prepared by').($o->createdBy ? ': '.$o->createdBy->name : ''), __('Checked by'), __('Authorised signature')],
        ];
    }

    private function goodsReceipt($r): array
    {
        $r->load('purchaseOrder.company', 'warehouse', 'createdBy', 'items.product.unit');

        return [
            'title' => __('Goods Receipt Note'),
            'number' => $r->number,
            'date' => $this->d($r->receipt_date),
            'meta' => [__('Purchase Order').': '.$r->purchaseOrder?->number, $r->supplier_invoice_no ? __('Supplier invoice').': '.$r->supplier_invoice_no : null],
            'parties' => [$this->companyParty($r->purchaseOrder?->company, __('Supplier')), $this->warehouseParty($r->warehouse, __('Received at'))],
            'columns' => [[__('Product'), 'l', null], [__('Batch No'), 'l', 14], [__('Expiry Date'), 'c', 12], [__('Quantity'), 'r', 10], [__('Unit Cost'), 'r', 12], [__('Amount'), 'r', 14]],
            'rows' => $r->items->map(fn ($i) => [$i->product?->name, $i->batch_no, $this->d($i->expiry_date), $this->q($i->quantity).' '.$this->unit($i->product), $this->m($i->unit_cost), $this->m($i->quantity * $i->unit_cost)])->all(),
            'totals' => (float) $r->vat_amount > 0
                ? [[__('Value'), $this->m($r->total_amount)], [__('Input VAT'), $this->m($r->vat_amount)], [__('Total'), $this->m((float) $r->total_amount + (float) $r->vat_amount), true]]
                : [[__('Total'), $this->m($r->total_amount), true]],
            'signatures' => [__('Received by').($r->createdBy ? ': '.$r->createdBy->name : ''), __('Store keeper'), __('Approved by')],
        ];
    }

    private function deliveryNote($n): array
    {
        $n->load('salesOrder.retailer', 'salesOrder.warehouse', 'deliveredBy', 'items.product.unit', 'items.batch');

        return [
            'title' => __('Delivery Challan'),
            'number' => $n->number,
            'date' => $this->d($n->delivery_date),
            'status' => $this->status($n->status),
            'meta' => [__('Sales Order').': '.$n->salesOrder?->number, $n->deliveredBy ? __('Delivered by').': '.$n->deliveredBy->name : null],
            'parties' => [$this->retailerParty($n->salesOrder?->retailer, __('Deliver to')), $this->warehouseParty($n->salesOrder?->warehouse, __('From warehouse'))],
            'columns' => [[__('Product'), 'l', null], [__('Batch No'), 'l', 18], [__('Expiry Date'), 'c', 14], [__('Quantity'), 'r', 14], [__('Unit'), 'c', 10]],
            'rows' => $n->items->map(fn ($i) => [$i->product?->name.' ('.$i->product?->sku.')', $i->batch?->batch_no, $this->d($i->batch?->expiry_date), $this->q($i->quantity), $this->unit($i->product)])->all(),
            'totals' => [[__('Total quantity'), $this->q($n->items->sum('quantity')), true]],
            'note' => __('Goods received in good condition.'),
            'signatures' => [__('Receiver\'s signature'), __('Delivered by'), __('Store keeper')],
        ];
    }

    private function returnRequest($r): array
    {
        $r->load('retailer', 'requestedBy', 'items.product.unit');

        return [
            'title' => __('Return Request'),
            'number' => $r->number,
            'date' => $this->d($r->request_date),
            'status' => $this->status($r->status),
            'parties' => [$this->retailerParty($r->retailer, __('Customer'))],
            'columns' => [[__('Product'), 'l', null], [__('Quantity'), 'r', 12], [__('Unit Price'), 'r', 14], [__('Amount'), 'r', 14], [__('Reason'), 'l', 24]],
            'rows' => $r->items->map(fn ($i) => [$i->product?->name, $this->q($i->quantity).' '.$this->unit($i->product), $this->m($i->unit_price), $this->m($i->quantity * $i->unit_price), $i->reason])->all(),
            'totals' => [[__('Total'), $this->m($r->items->sum(fn ($i) => $i->quantity * $i->unit_price)), true]],
            'note' => $r->reason,
            'signatures' => [__('Customer'), __('Requested by').($r->requestedBy ? ': '.$r->requestedBy->name : ''), __('Approved by')],
        ];
    }

    private function purchaseReturn($r): array
    {
        $r->load('company', 'warehouse', 'items.product.unit', 'items.batch');

        return [
            'title' => __('Purchase Return'),
            'number' => $r->number,
            'date' => $this->d($r->return_date),
            'status' => $this->status($r->status),
            'parties' => [$this->companyParty($r->company, __('Supplier')), $this->warehouseParty($r->warehouse, __('From warehouse'))],
            'columns' => [[__('Product'), 'l', null], [__('Batch No'), 'l', 16], [__('Quantity'), 'r', 12], [__('Unit Cost'), 'r', 14], [__('Amount'), 'r', 16]],
            'rows' => $r->items->map(fn ($i) => [$i->product?->name, $i->batch?->batch_no, $this->q($i->quantity).' '.$this->unit($i->product), $this->m($i->unit_cost), $this->m($i->quantity * $i->unit_cost)])->all(),
            'totals' => [[__('Total'), $this->m($r->total_amount), true]],
            'words' => (float) $r->total_amount,
            'note' => $r->reason,
        ];
    }

    private function companyReturn($r): array
    {
        $r->load('company', 'warehouse', 'items.product.unit');

        return [
            'title' => __('Company Return'),
            'number' => $r->number,
            'date' => $this->d($r->return_date),
            'status' => $this->status($r->status),
            'parties' => [$this->companyParty($r->company, __('Company')), $this->warehouseParty($r->warehouse, __('From warehouse'))],
            'columns' => [[__('Product'), 'l', null], [__('SKU'), 'l', 20], [__('Quantity'), 'r', 14], [__('Unit'), 'c', 10]],
            'rows' => $r->items->map(fn ($i) => [$i->product?->name, $i->product?->sku, $this->q($i->quantity), $this->unit($i->product)])->all(),
            'totals' => [[__('Total quantity'), $this->q($r->items->sum('quantity')), true]],
        ];
    }

    private function stockTransfer($t): array
    {
        $t->load('fromWarehouse', 'toWarehouse', 'creator', 'items.product.unit', 'items.batch');

        return [
            'title' => __('Stock Transfer Note'),
            'number' => $t->number,
            'date' => $this->d($t->transfer_date),
            'parties' => [$this->warehouseParty($t->fromWarehouse, __('From')), $this->warehouseParty($t->toWarehouse, __('To'))],
            'columns' => [[__('Product'), 'l', null], [__('Batch No'), 'l', 18], [__('Expiry Date'), 'c', 14], [__('Quantity'), 'r', 14], [__('Unit'), 'c', 10]],
            'rows' => $t->items->map(fn ($i) => [$i->product?->name, $i->batch?->batch_no, $this->d($i->batch?->expiry_date), $this->q($i->quantity), $this->unit($i->product)])->all(),
            'totals' => [[__('Total quantity'), $this->q($t->items->sum('quantity')), true]],
            'note' => $t->notes,
            'signatures' => [__('Sent by').($t->creator ? ': '.$t->creator->name : ''), __('Carried by'), __('Received by')],
        ];
    }

    private function damage($r): array
    {
        $r->load('product.unit', 'warehouse', 'batch', 'reporter', 'approver');

        return [
            'title' => __('Damage Report'),
            'number' => $r->number,
            'date' => $this->d($r->damage_date),
            'status' => $this->status($r->status),
            'meta' => [__('Source').': '.$r->sourceLabel()],
            'parties' => [$this->warehouseParty($r->warehouse, __('Warehouse'))],
            'columns' => [[__('Product'), 'l', null], [__('Batch No'), 'l', 16], [__('Quantity'), 'r', 12], [__('Unit Cost'), 'r', 14], [__('Value'), 'r', 16]],
            'rows' => [[$r->product?->name, $r->batch?->batch_no ?? $r->batch_no, $this->q($r->quantity).' '.$this->unit($r->product), $this->m($r->unit_cost), $this->m($r->quantity * $r->unit_cost)]],
            'totals' => [[__('Total'), $this->m($r->quantity * $r->unit_cost), true]],
            'note' => trim($r->reason.($r->decision_note ? ' — '.$r->decision_note : '')),
            'signatures' => [__('Reported by').($r->reporter ? ': '.$r->reporter->name : ''), __('Checked by'), __('Approved by').($r->approver ? ': '.$r->approver->name : '')],
        ];
    }

    private function warrantyClaim($c): array
    {
        $c->load('retailer', 'product', 'salesInvoice');

        return [
            'title' => __('Warranty Claim'),
            'number' => $c->number,
            'date' => $this->d($c->claim_date),
            'status' => $this->status($c->status),
            'meta' => [$c->salesInvoice ? __('Invoice').': '.$c->salesInvoice->number : null],
            'parties' => [$this->retailerParty($c->retailer, __('Customer'))],
            'columns' => [[__('Product'), 'l', null], [__('SKU'), 'l', 20], [__('Issue'), 'l', 40]],
            'rows' => [[$c->product?->name, $c->product?->sku, $c->issue_description]],
            'signatures' => [__('Customer'), __('Received by'), __('Approved by')],
        ];
    }
}
