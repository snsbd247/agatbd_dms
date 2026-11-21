<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Delivery\DeliveryNote;
use App\Models\Delivery\DeliveryNoteItem;
use App\Models\PaymentCollection;
use App\Models\Payroll\Advance;
use App\Models\Payroll\Bonus;
use App\Models\Payroll\Commission;
use App\Models\Payroll\PayrollEntry;
use App\Models\Payroll\RepresentativeTarget;
use App\Models\Product;
use App\Models\Purchase\GoodsReceipt;
use App\Models\Purchase\GoodsReceiptItem;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderItem;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Purchase\PurchaseReturnItem;
use App\Models\Representative;
use App\Models\Retailer;
use App\Models\Returns\CompanyReturn;
use App\Models\Returns\CompanyReturnItem;
use App\Models\Returns\ReturnReceive;
use App\Models\Returns\ReturnReceiveItem;
use App\Models\Returns\ReturnRequest;
use App\Models\Returns\ReturnRequestItem;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceItem;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Warranty\WarrantyClaim;
use App\Notifications\ReturnRequestPending;
use App\Notifications\SalesOrderPending;
use App\Notifications\WarrantyClaimPending;
use App\Services\InventoryService;
use App\Services\NumberGeneratorService;
use App\Services\PaymentAllocationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class DemoTransactionsSeeder extends Seeder
{
    public function run(): void
    {
        if (SalesOrder::count() > 0) {
            $this->command?->info('Transactional demo data already present, skipping.');

            return;
        }

        $inventory = app(InventoryService::class);
        $numbers = app(NumberGeneratorService::class);
        $allocator = app(PaymentAllocationService::class);

        $warehouse = Warehouse::where('name', 'Central Warehouse')->firstOrFail();
        $retailer = Retailer::where('code', 'RTL-001')->firstOrFail();
        $representative = Representative::where('employee_code', 'REP-001')->firstOrFail();
        $dealer = User::where('email', 'admin@dms.local')->firstOrFail();

        $products = Product::pluck('id', 'sku');
        $companies = Company::pluck('id', 'code');

        $batchByProduct = [];

        // 1. Purchase Orders + Goods Receipts (bring real stock into the warehouse)
        $poDefs = [
            ['company' => 'UNBD', 'daysAgo' => 8, 'items' => [
                ['sku' => 'LUX-SOAP-100', 'qty' => 200, 'cost' => 25],
                ['sku' => 'SUN-SHMP-200', 'qty' => 100, 'cost' => 140],
                ['sku' => 'LUX-BODYWASH-200', 'qty' => 80, 'cost' => 150],
                ['sku' => 'SUNSILK-CTN-24', 'qty' => 20, 'cost' => 3200],
            ]],
            ['company' => 'ACI', 'daysAgo' => 7, 'items' => [
                ['sku' => 'SAVLON-HW-250', 'qty' => 60, 'cost' => 90],
                ['sku' => 'SAVLON-AS-100', 'qty' => 50, 'cost' => 45],
            ]],
            ['company' => 'SQTL', 'daysAgo' => 7, 'items' => [
                ['sku' => 'MERIL-TALC-100', 'qty' => 100, 'cost' => 60],
                ['sku' => 'MERIL-BOX-50', 'qty' => 10, 'cost' => 2800],
            ]],
        ];

        foreach ($poDefs as $def) {
            $orderDate = today()->subDays($def['daysAgo']);
            $receiptDate = $orderDate->copy()->addDay();

            $po = PurchaseOrder::create([
                'number' => $numbers->generate('purchase_order'),
                'company_id' => $companies[$def['company']],
                'warehouse_id' => $warehouse->id,
                'status' => 'sent',
                'order_date' => $orderDate,
                'expected_date' => $orderDate->copy()->addDays(3),
                'total_amount' => collect($def['items'])->sum(fn ($i) => $i['qty'] * $i['cost']),
                'created_by' => $dealer->id,
            ]);

            $receipt = GoodsReceipt::create([
                'number' => $numbers->generate('goods_receipt'),
                'purchase_order_id' => $po->id,
                'warehouse_id' => $warehouse->id,
                'receipt_date' => $receiptDate,
                'total_amount' => 0,
                'created_by' => $dealer->id,
            ]);

            $receiptTotal = 0;

            foreach ($def['items'] as $item) {
                $poItem = PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $products[$item['sku']],
                    'quantity' => $item['qty'],
                    'unit_price' => $item['cost'],
                ]);

                $batchNo = $item['sku'].'-B1';
                $expiry = today()->addYear()->toDateString();

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $products[$item['sku']],
                    'batch_no' => $batchNo,
                    'expiry_date' => $expiry,
                    'quantity' => $item['qty'],
                    'unit_cost' => $item['cost'],
                ]);

                $batch = $inventory->receiveStock(
                    productId: $products[$item['sku']],
                    warehouseId: $warehouse->id,
                    batchNo: $batchNo,
                    expiryDate: $expiry,
                    costPrice: $item['cost'],
                    quantity: $item['qty'],
                    reference: $receipt,
                );

                $batchByProduct[$item['sku']] = $batch->id;

                // Stock.reorder_level is never populated from Product.reorder_level anywhere
                // in the app (confirmed pre-existing gap, not introduced here) — set it here so
                // the demo data's low-stock alerts/reports actually have something to show.
                \App\Models\Stock::where('product_id', $products[$item['sku']])
                    ->where('warehouse_id', $warehouse->id)
                    ->update(['reorder_level' => Product::find($products[$item['sku']])->reorder_level]);

                $poItem->increment('received_quantity', $item['qty']);
                $receiptTotal += $item['qty'] * $item['cost'];
            }

            $receipt->update(['total_amount' => $receiptTotal]);
            $po->update(['status' => 'completed']);
        }

        // 2. Sales Orders — one pending, one approved-only, three approved+delivered+invoiced
        $soDefs = [
            ['daysAgo' => 6, 'status' => 'pending', 'deliver' => false, 'items' => [
                ['sku' => 'LUX-SOAP-100', 'qty' => 30, 'price' => 32],
            ]],
            ['daysAgo' => 5, 'status' => 'approved', 'deliver' => false, 'items' => [
                ['sku' => 'SUN-SHMP-200', 'qty' => 10, 'price' => 175],
                ['sku' => 'LUX-BODYWASH-200', 'qty' => 5, 'price' => 190],
            ]],
            ['daysAgo' => 4, 'status' => 'approved', 'deliver' => true, 'items' => [
                ['sku' => 'LUX-SOAP-100', 'qty' => 50, 'price' => 32],
                ['sku' => 'SAVLON-HW-250', 'qty' => 20, 'price' => 115],
            ]],
            ['daysAgo' => 2, 'status' => 'approved', 'deliver' => true, 'items' => [
                ['sku' => 'MERIL-TALC-100', 'qty' => 40, 'price' => 78],
            ]],
            ['daysAgo' => 1, 'status' => 'approved', 'deliver' => true, 'items' => [
                ['sku' => 'MERIL-BOX-50', 'qty' => 6, 'price' => 3500],
            ]],
        ];

        $pendingOrder = null;
        $invoices = [];

        foreach ($soDefs as $def) {
            $orderDate = today()->subDays($def['daysAgo']);
            $subtotal = collect($def['items'])->sum(fn ($i) => $i['qty'] * $i['price']);

            $order = SalesOrder::create([
                'number' => $numbers->generate('sales_order'),
                'order_type' => 'representative',
                'retailer_id' => $retailer->id,
                'representative_id' => $representative->id,
                'warehouse_id' => $warehouse->id,
                'status' => $def['status'],
                'order_date' => $orderDate,
                'subtotal' => $subtotal,
                'discount' => 0,
                'grand_total' => $subtotal,
                'approved_by' => $def['status'] === 'approved' ? $dealer->id : null,
                'approved_at' => $def['status'] === 'approved' ? $orderDate : null,
            ]);

            foreach ($def['items'] as $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $products[$item['sku']],
                    'quantity' => $item['qty'],
                    'unit_price' => $item['price'],
                ]);
            }

            if ($def['status'] === 'pending' && ! $pendingOrder) {
                $pendingOrder = $order;
            }

            if ($def['deliver']) {
                $invoices[] = $this->deliverOrder($order, $inventory, $numbers, $dealer, $orderDate->copy()->addDay());
            }
        }

        // 3. Collections (FIFO allocation across the invoices just created — real service, real behavior)
        $collection1 = PaymentCollection::create([
            'number' => $numbers->generate('collection'),
            'retailer_id' => $retailer->id,
            'collected_by' => $representative->user_id,
            'collection_date' => today()->subDays(2),
            'amount' => 2000,
            'method' => 'cash',
            'reference_no' => null,
        ]);
        $allocator->allocate($retailer, 2000, $collection1);

        $collection2 = PaymentCollection::create([
            'number' => $numbers->generate('collection'),
            'retailer_id' => $retailer->id,
            'collected_by' => $representative->user_id,
            'collection_date' => today()->subDay(),
            'amount' => 3120,
            'method' => 'bank',
            'reference_no' => 'BANK-REF-0001',
        ]);
        $allocator->allocate($retailer, 3120, $collection2);

        // 4. Returns — one fully processed with mixed disposition, one left pending
        $return1 = ReturnRequest::create([
            'number' => $numbers->generate('return_request'),
            'retailer_id' => $retailer->id,
            'requested_by' => $representative->user_id,
            'request_date' => today()->subDays(3),
            'status' => 'approved',
            'reason' => 'Damaged in transit',
        ]);
        ReturnRequestItem::create(['return_request_id' => $return1->id, 'product_id' => $products['LUX-SOAP-100'], 'quantity' => 5, 'unit_price' => 32, 'reason' => 'Damaged packaging']);
        ReturnRequestItem::create(['return_request_id' => $return1->id, 'product_id' => $products['SAVLON-HW-250'], 'quantity' => 3, 'unit_price' => 115, 'reason' => 'Wrong variant']);

        $receive = ReturnReceive::create([
            'number' => $numbers->generate('return_receive'),
            'return_request_id' => $return1->id,
            'received_by' => $dealer->id,
            'warehouse_id' => $warehouse->id,
            'receive_date' => today()->subDays(2),
        ]);

        $restockBatch = $inventory->receiveStock(
            productId: $products['LUX-SOAP-100'],
            warehouseId: $warehouse->id,
            batchNo: 'LUX-SOAP-100-RET1',
            expiryDate: today()->addYear()->toDateString(),
            costPrice: 25,
            quantity: 5,
            reference: $receive,
        );
        ReturnReceiveItem::create(['return_receive_id' => $receive->id, 'product_id' => $products['LUX-SOAP-100'], 'batch_id' => $restockBatch->id, 'quantity' => 5, 'disposition' => 'restock']);

        $companyReturn = CompanyReturn::firstOrCreate(
            ['company_id' => $companies['ACI'], 'warehouse_id' => $warehouse->id, 'status' => 'draft'],
            ['number' => $numbers->generate('company_return'), 'return_date' => today()]
        );
        CompanyReturnItem::create(['company_return_id' => $companyReturn->id, 'product_id' => $products['SAVLON-HW-250'], 'quantity' => 3]);
        ReturnReceiveItem::create(['return_receive_id' => $receive->id, 'product_id' => $products['SAVLON-HW-250'], 'batch_id' => null, 'quantity' => 3, 'disposition' => 'company_return']);

        $retailerCredit = (5 * 32) + (3 * 115);
        $retailer = Retailer::whereKey($retailer->id)->lockForUpdate()->first();
        $retailer->decrement('current_due', $retailerCredit);
        $retailer->ledgerEntries()->create([
            'type' => 'return',
            'reference_type' => $receive->getMorphClass(),
            'reference_id' => $receive->getKey(),
            'debit' => 0,
            'credit' => $retailerCredit,
            'balance_after' => $retailer->current_due,
            'entry_date' => today()->subDays(2),
        ]);
        $return1->update(['status' => 'received']);

        $return2 = ReturnRequest::create([
            'number' => $numbers->generate('return_request'),
            'retailer_id' => $retailer->id,
            'requested_by' => $representative->user_id,
            'request_date' => today()->subDay(),
            'status' => 'pending',
            'reason' => 'Wrong item shipped',
        ]);
        ReturnRequestItem::create(['return_request_id' => $return2->id, 'product_id' => $products['MERIL-BOX-50'], 'quantity' => 1, 'unit_price' => 3500, 'reason' => 'Wrong item shipped']);

        // 5. Warranty Claim
        $warrantyClaim = WarrantyClaim::create([
            'number' => $numbers->generate('warranty_claim'),
            'retailer_id' => $retailer->id,
            'product_id' => $products['MERIL-TALC-100'],
            'sales_invoice_id' => $invoices[1]->id,
            'claim_date' => today(),
            'issue_description' => 'Product seal broken on arrival, customer requesting replacement.',
            'status' => 'pending',
        ]);

        // 6. Purchase Return — send a small quantity of a received batch back to the supplier
        $purchaseReturn = PurchaseReturn::create([
            'number' => $numbers->generate('purchase_return'),
            'company_id' => $companies['ACI'],
            'warehouse_id' => $warehouse->id,
            'return_date' => today(),
            'reason' => 'Received expired stock',
            'status' => 'draft',
            'total_amount' => 5 * 45,
        ]);
        PurchaseReturnItem::create([
            'purchase_return_id' => $purchaseReturn->id,
            'product_id' => $products['SAVLON-AS-100'],
            'batch_id' => $batchByProduct['SAVLON-AS-100'],
            'quantity' => 5,
            'unit_cost' => 45,
        ]);
        $inventory->returnToSupplier($batchByProduct['SAVLON-AS-100'], 5, $purchaseReturn);
        $purchaseReturn->update(['status' => 'sent']);

        // 7. Payroll — advance + bonus, then a generated entry using the exact Payroll\Entries\Create::generate() formula
        Advance::create([
            'representative_id' => $representative->id,
            'amount' => 1000,
            'advance_date' => today()->subDays(4),
            'reason' => 'Emergency travel advance',
            'deducted' => false,
        ]);
        Bonus::create([
            'representative_id' => $representative->id,
            'amount' => 500,
            'bonus_date' => today()->subDays(3),
            'reason' => 'Best performer of the month',
        ]);

        $period = today()->format('Y-m');
        $start = today()->copy()->startOfMonth();
        $end = today()->copy()->endOfMonth();

        $deliveredAmount = (float) SalesInvoice::whereHas('salesOrder', fn ($q) => $q->where('representative_id', $representative->id))
            ->whereBetween('invoice_date', [$start, $end])
            ->sum('grand_total');

        $retailerIds = Retailer::where('representative_id', $representative->id)->pluck('id');
        $returnAdjustment = (float) ReturnRequestItem::whereHas('returnRequest', fn ($q) => $q->where('status', 'received')
            ->whereIn('retailer_id', $retailerIds)
            ->whereBetween('request_date', [$start, $end]))
            ->get()
            ->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);

        $commissionBase = max(0, $deliveredAmount - $returnAdjustment);
        $commissionAmount = round($commissionBase * (float) $representative->commission_rate / 100, 2);

        Commission::create([
            'representative_id' => $representative->id,
            'sales_invoice_id' => null,
            'period' => $period,
            'delivered_amount' => $deliveredAmount,
            'commission_rate' => $representative->commission_rate,
            'commission_amount' => $commissionAmount,
            'return_adjustment' => $returnAdjustment,
        ]);

        $bonusAmount = (float) Bonus::where('representative_id', $representative->id)
            ->whereBetween('bonus_date', [$start, $end])
            ->sum('amount');

        $advanceDeduction = (float) Advance::where('representative_id', $representative->id)->where('deducted', false)->sum('amount');
        Advance::where('representative_id', $representative->id)->where('deducted', false)->update(['deducted' => true]);

        $baseSalary = (float) $representative->salary_base;
        $netSalary = $baseSalary + $commissionAmount + $bonusAmount - $advanceDeduction;

        PayrollEntry::create([
            'number' => $numbers->generate('payroll_entry'),
            'representative_id' => $representative->id,
            'period' => $period,
            'base_salary' => $baseSalary,
            'commission_amount' => $commissionAmount,
            'bonus_amount' => $bonusAmount,
            'advance_deduction' => $advanceDeduction,
            'other_deduction' => 0,
            'net_salary' => $netSalary,
            'status' => 'pending',
        ]);

        RepresentativeTarget::updateOrCreate(
            ['representative_id' => $representative->id, 'period' => $period],
            ['target_amount' => $representative->target_amount, 'achieved_amount' => $deliveredAmount]
        );

        // 8. Notifications for the items left in a pending state (LowStockAlert already fired
        // naturally from InventoryService::consumeStock during the MERIL-BOX-50 delivery above).
        $recipients = User::role(['Admin', 'Manager'])->get();
        Notification::send($recipients, new SalesOrderPending($pendingOrder));
        Notification::send($recipients, new ReturnRequestPending($return2));
        Notification::send($recipients, new WarrantyClaimPending($warrantyClaim));

        $this->command?->info('Full transactional demo data seeded.');
    }

    private function deliverOrder(SalesOrder $order, InventoryService $inventory, NumberGeneratorService $numbers, User $dealer, $deliveryDate): SalesInvoice
    {
        $deliveryNote = DeliveryNote::create([
            'number' => $numbers->generate('delivery_note'),
            'sales_order_id' => $order->id,
            'delivery_type' => 'representative',
            'delivered_by' => $dealer->id,
            'delivery_date' => $deliveryDate,
            'status' => 'delivered',
        ]);

        $invoiceTotal = 0;
        $invoiceLineData = [];

        foreach ($order->items as $item) {
            $consumed = $inventory->consumeStock(
                productId: $item->product_id,
                warehouseId: $order->warehouse_id,
                quantity: (float) $item->quantity,
                reference: $deliveryNote,
            );

            foreach ($consumed as $split) {
                DeliveryNoteItem::create([
                    'delivery_note_id' => $deliveryNote->id,
                    'sales_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'batch_id' => $split['batch_id'],
                    'quantity' => $split['quantity'],
                ]);

                $lineTotal = $split['quantity'] * $item->unit_price;
                $invoiceTotal += $lineTotal;
                $invoiceLineData[] = [
                    'product_id' => $item->product_id,
                    'batch_id' => $split['batch_id'],
                    'quantity' => $split['quantity'],
                    'unit_price' => $item->unit_price,
                    'line_total' => $lineTotal,
                ];
            }

            $item->increment('delivered_quantity', $item->quantity);
        }

        $invoice = SalesInvoice::create([
            'number' => $numbers->generate('sales_invoice'),
            'sales_order_id' => $order->id,
            'retailer_id' => $order->retailer_id,
            'invoice_date' => $deliveryDate,
            'subtotal' => $invoiceTotal,
            'discount' => 0,
            'grand_total' => $invoiceTotal,
            'paid_amount' => 0,
            'due_amount' => $invoiceTotal,
            'status' => 'unpaid',
        ]);

        foreach ($invoiceLineData as $line) {
            SalesInvoiceItem::create(['sales_invoice_id' => $invoice->id, ...$line]);
        }

        $retailer = Retailer::whereKey($order->retailer_id)->lockForUpdate()->first();
        $retailer->increment('current_due', $invoiceTotal);
        $retailer->ledgerEntries()->create([
            'type' => 'invoice',
            'reference_type' => $invoice->getMorphClass(),
            'reference_id' => $invoice->getKey(),
            'debit' => $invoiceTotal,
            'credit' => 0,
            'balance_after' => $retailer->current_due,
            'entry_date' => $deliveryDate,
        ]);

        $order->update(['status' => 'completed']);

        return $invoice;
    }
}
