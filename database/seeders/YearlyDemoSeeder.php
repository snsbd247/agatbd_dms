<?php

namespace Database\Seeders;

use App\Models\Area;
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

class YearlyDemoSeeder extends Seeder
{
    /**
     * Demo users get DEMO_PASSWORD from .env, or a random password when it is
     * not set. Never hard-code a shared password here: this app is reachable
     * from the internet.
     */
    private function demoPassword(): string
    {
        return env('DEMO_PASSWORD') ?: \Illuminate\Support\Str::password(16);
    }

    private InventoryService $inventory;

    private NumberGeneratorService $numbers;

    private PaymentAllocationService $allocator;

    private Warehouse $warehouse;

    private User $dealer;

    /** @var \Illuminate\Support\Collection<string,int> */
    private $companies;

    /** @var \Illuminate\Support\Collection<string,Product> */
    private $products;

    /** @var array<int, string> */
    private array $skus;

    /** @var \Illuminate\Support\Collection<int,Retailer> */
    private $retailers;

    /** @var \Illuminate\Support\Collection<int,Representative> */
    private $representatives;

    private const PURCHASE_BASE_QTY = [
        'LUX-SOAP-100' => 250,
        'SUN-SHMP-200' => 120,
        'MERIL-TALC-100' => 150,
        'SAVLON-HW-250' => 100,
        'SAVLON-AS-100' => 120,
        'LUX-BODYWASH-200' => 90,
        'MERIL-BOX-50' => 15,
        'SUNSILK-CTN-24' => 20,
    ];

    public function run(): void
    {
        if (SalesInvoice::count() > 0) {
            $this->command?->info('Yearly demo data already present, skipping.');

            return;
        }

        $this->inventory = app(InventoryService::class);
        $this->numbers = app(NumberGeneratorService::class);
        $this->allocator = app(PaymentAllocationService::class);
        $this->warehouse = Warehouse::where('name', 'Central Warehouse')->firstOrFail();
        $this->dealer = User::where('email', 'admin@dms.local')->firstOrFail();
        $this->companies = Company::pluck('id', 'code');
        $this->products = Product::all()->keyBy('sku');
        $this->skus = array_keys(self::PURCHASE_BASE_QTY);

        $this->setupExtraMasterData();

        for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
            $monthIndex = 11 - $monthsAgo;
            $monthStart = today()->copy()->subMonths($monthsAgo)->startOfMonth();
            $isCurrentMonth = $monthsAgo === 0;

            $lastBatchBySku = $this->seedPurchases($monthIndex, $monthStart);
            $monthInvoicesByRetailer = $this->seedSales($monthIndex, $monthStart, $isCurrentMonth);
            $this->seedCollections($monthInvoicesByRetailer);
            $this->seedReturns($monthIndex, $monthStart, $isCurrentMonth);
            $this->seedWarranty($monthIndex, $monthStart, $isCurrentMonth, $monthInvoicesByRetailer);
            $this->seedPayroll($monthIndex, $monthStart, $isCurrentMonth, $monthInvoicesByRetailer);

            if ($isCurrentMonth) {
                $this->seedPurchaseReturn($monthStart, $lastBatchBySku);
            }
        }

        $this->command?->info('Full year of demo data seeded across all modules.');
    }

    private function setupExtraMasterData(): void
    {
        $areaNorth = Area::where('code', 'DHK-N')->firstOrFail();
        $areaSouth = Area::where('code', 'DHK-S')->firstOrFail();

        $rep1 = Representative::where('employee_code', 'REP-001')->firstOrFail();

        $rep2User = User::firstOrCreate(
            ['email' => 'rep2@dms.local'],
            ['name' => 'Jasim Representative', 'password' => $this->demoPassword()]
        );
        $rep2User->assignRole('Sales Representative');

        $rep2 = Representative::firstOrCreate(
            ['employee_code' => 'REP-002'],
            [
                'user_id' => $rep2User->id,
                'name' => 'Jasim Representative',
                'phone' => '01700000002',
                'area_id' => $areaSouth->id,
                'salary_base' => 14000,
                'commission_rate' => 2.5,
                'target_amount' => 180000,
                'active' => true,
            ]
        );

        $retailer1 = Retailer::where('code', 'RTL-001')->firstOrFail();

        $retailerDefs = [
            ['code' => 'RTL-002', 'name' => 'Hasan Store', 'shop' => 'Hasan General Store', 'phone' => '01800000002', 'area' => $areaNorth, 'rep' => $rep1, 'addr' => 'Pallabi, Dhaka'],
            ['code' => 'RTL-003', 'name' => 'Nasrin Traders', 'shop' => 'Nasrin Traders', 'phone' => '01800000003', 'area' => $areaSouth, 'rep' => $rep2, 'addr' => 'Jatrabari, Dhaka'],
            ['code' => 'RTL-004', 'name' => 'Salam Bazar', 'shop' => 'Salam Bazar Store', 'phone' => '01800000004', 'area' => $areaSouth, 'rep' => $rep2, 'addr' => 'Sayedabad, Dhaka'],
        ];

        $retailers = collect([$retailer1]);

        foreach ($retailerDefs as $index => $def) {
            $user = User::firstOrCreate(
                ['email' => 'retailer'.($index + 2).'@dms.local'],
                ['name' => $def['name'], 'password' => $this->demoPassword()]
            );
            $user->assignRole('Retailer');

            $retailers->push(Retailer::firstOrCreate(
                ['code' => $def['code']],
                [
                    'user_id' => $user->id,
                    'representative_id' => $def['rep']->id,
                    'area_id' => $def['area']->id,
                    'name' => $def['name'],
                    'shop_name' => $def['shop'],
                    'phone' => $def['phone'],
                    'address' => $def['addr'],
                    'credit_limit' => 60000,
                    'current_due' => 0,
                    'active' => true,
                ]
            ));
        }

        $this->retailers = $retailers->values();
        $this->representatives = collect([$rep1, $rep2]);
    }

    /** @return array<string,int> last batch id created per sku this month */
    private function seedPurchases(int $monthIndex, $monthStart): array
    {
        $growth = 1 + ($monthIndex * 0.05);
        $orderDate = $monthStart->copy()->addDays(2);
        $receiptDate = $orderDate->copy()->addDay();
        $lastBatchBySku = [];

        foreach (['UNBD', 'SQTL', 'ACI'] as $companyCode) {
            $companyProducts = $this->products->filter(fn (Product $p) => $p->company_id === $this->companies[$companyCode]);

            if ($companyProducts->isEmpty()) {
                continue;
            }

            $items = $companyProducts->map(fn (Product $p) => [
                'sku' => $p->sku,
                'qty' => (float) round(self::PURCHASE_BASE_QTY[$p->sku] * $growth),
                'cost' => (float) $p->purchase_price,
            ])->values()->all();

            $po = PurchaseOrder::create([
                'number' => $this->numbers->generate('purchase_order'),
                'company_id' => $this->companies[$companyCode],
                'warehouse_id' => $this->warehouse->id,
                'status' => 'sent',
                'order_date' => $orderDate,
                'expected_date' => $orderDate->copy()->addDays(3),
                'total_amount' => collect($items)->sum(fn ($i) => $i['qty'] * $i['cost']),
                'created_by' => $this->dealer->id,
            ]);

            $receipt = GoodsReceipt::create([
                'number' => $this->numbers->generate('goods_receipt'),
                'purchase_order_id' => $po->id,
                'warehouse_id' => $this->warehouse->id,
                'receipt_date' => $receiptDate,
                'total_amount' => 0,
                'created_by' => $this->dealer->id,
            ]);

            $receiptTotal = 0;

            foreach ($items as $item) {
                $product = $this->products[$item['sku']];

                $poItem = PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $product->id,
                    'quantity' => $item['qty'],
                    'unit_price' => $item['cost'],
                ]);

                $batchNo = $item['sku'].'-B'.($monthIndex + 1);
                $expiry = $receiptDate->copy()->addYear()->toDateString();

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $product->id,
                    'batch_no' => $batchNo,
                    'expiry_date' => $expiry,
                    'quantity' => $item['qty'],
                    'unit_cost' => $item['cost'],
                ]);

                $batch = $this->inventory->receiveStock(
                    productId: $product->id,
                    warehouseId: $this->warehouse->id,
                    batchNo: $batchNo,
                    expiryDate: $expiry,
                    costPrice: $item['cost'],
                    quantity: $item['qty'],
                    reference: $receipt,
                );

                $lastBatchBySku[$item['sku']] = $batch->id;

                \App\Models\Stock::where('product_id', $product->id)
                    ->where('warehouse_id', $this->warehouse->id)
                    ->update(['reorder_level' => $product->reorder_level]);

                $poItem->increment('received_quantity', $item['qty']);
                $receiptTotal += $item['qty'] * $item['cost'];
            }

            $receipt->update(['total_amount' => $receiptTotal]);
            $po->update(['status' => 'completed']);
        }

        return $lastBatchBySku;
    }

    /** @return array<int, \Illuminate\Support\Collection<int,SalesInvoice>> invoices created this month, keyed by retailer id */
    private function seedSales(int $monthIndex, $monthStart, bool $isCurrentMonth): array
    {
        $daysInMonth = $monthStart->daysInMonth;
        $invoicesByRetailer = [];
        $pendingOrder = null;

        foreach ($this->retailers as $retailerIndex => $retailer) {
            $invoicesByRetailer[$retailer->id] = collect();

            foreach ([8, min(20, $daysInMonth)] as $orderIndex => $day) {
                $orderDate = $monthStart->copy()->addDays(min($day, $daysInMonth) - 1);
                $skuCount = count($this->skus);
                $startIdx = ($retailerIndex + $monthIndex + $orderIndex) % $skuCount;
                $lineSkus = [$this->skus[$startIdx], $this->skus[($startIdx + 1) % $skuCount]];

                $items = [];
                foreach ($lineSkus as $sku) {
                    $product = $this->products[$sku];
                    $qty = max(2, intdiv(self::PURCHASE_BASE_QTY[$sku], 12)) + $retailerIndex + intdiv($monthIndex, 4);
                    $items[] = ['sku' => $sku, 'qty' => (float) $qty, 'price' => (float) $product->sale_price];
                }

                $subtotal = collect($items)->sum(fn ($i) => $i['qty'] * $i['price']);
                $leaveUndelivered = $isCurrentMonth && $orderIndex === 1;

                $order = SalesOrder::create([
                    'number' => $this->numbers->generate('sales_order'),
                    'order_type' => 'representative',
                    'retailer_id' => $retailer->id,
                    'representative_id' => $retailer->representative_id,
                    'warehouse_id' => $this->warehouse->id,
                    'status' => $leaveUndelivered ? 'pending' : 'approved',
                    'order_date' => $orderDate,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'grand_total' => $subtotal,
                    'approved_by' => $leaveUndelivered ? null : $this->dealer->id,
                    'approved_at' => $leaveUndelivered ? null : $orderDate,
                ]);

                foreach ($items as $item) {
                    SalesOrderItem::create([
                        'sales_order_id' => $order->id,
                        'product_id' => $this->products[$item['sku']]->id,
                        'quantity' => $item['qty'],
                        'unit_price' => $item['price'],
                    ]);
                }

                if ($leaveUndelivered) {
                    $pendingOrder = $order;

                    continue;
                }

                try {
                    $invoice = $this->deliverOrder($order, $orderDate->copy()->addDay());
                    $invoicesByRetailer[$retailer->id]->push($invoice);
                } catch (\RuntimeException $e) {
                    $this->command?->warn("Skipped a sales line (insufficient stock): {$e->getMessage()}");
                }
            }
        }

        if ($pendingOrder) {
            $recipients = User::role(['Dealer', 'Manager'])->get()->filter(fn (User $u) => $u->wantsNotification('sales_order_pending'));
            Notification::send($recipients, new SalesOrderPending($pendingOrder));
        }

        return $invoicesByRetailer;
    }

    private function deliverOrder(SalesOrder $order, $deliveryDate): SalesInvoice
    {
        $deliveryNote = DeliveryNote::create([
            'number' => $this->numbers->generate('delivery_note'),
            'sales_order_id' => $order->id,
            'delivery_type' => 'representative',
            'delivered_by' => $this->dealer->id,
            'delivery_date' => $deliveryDate,
            'status' => 'delivered',
        ]);

        $invoiceTotal = 0;
        $invoiceLineData = [];

        foreach ($order->items as $item) {
            $consumed = $this->inventory->consumeStock(
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
            'number' => $this->numbers->generate('sales_invoice'),
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

    /** @param array<int, \Illuminate\Support\Collection<int,SalesInvoice>> $invoicesByRetailer */
    private function seedCollections(array $invoicesByRetailer): void
    {
        foreach ($this->retailers as $retailer) {
            $fresh = Retailer::find($retailer->id);

            if (! $fresh || (float) $fresh->current_due <= 0) {
                continue;
            }

            $amount = round((float) $fresh->current_due * 0.8, 2);

            if ($amount < 1) {
                continue;
            }

            $collection = PaymentCollection::create([
                'number' => $this->numbers->generate('collection'),
                'retailer_id' => $fresh->id,
                'collected_by' => $retailer->representative?->user_id,
                'collection_date' => today(),
                'amount' => $amount,
                'method' => $amount > 5000 ? 'bank' : 'cash',
                'reference_no' => $amount > 5000 ? 'BANK-REF-'.$fresh->id.'-'.$fresh->current_due : null,
            ]);

            $this->allocator->allocate($fresh, $amount, $collection);
        }
    }

    private function seedReturns(int $monthIndex, $monthStart, bool $isCurrentMonth): void
    {
        if ($monthIndex % 3 !== 0) {
            return;
        }

        $retailer = $this->retailers[$monthIndex % $this->retailers->count()];
        $sku = $this->skus[$monthIndex % count($this->skus)];
        $product = $this->products[$sku];
        $requestDate = $monthStart->copy()->addDays(15);

        $returnRequest = ReturnRequest::create([
            'number' => $this->numbers->generate('return_request'),
            'retailer_id' => $retailer->id,
            'requested_by' => $retailer->representative?->user_id,
            'request_date' => $requestDate,
            'status' => 'approved',
            'reason' => 'Damaged in transit',
        ]);
        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => $product->sale_price,
            'reason' => 'Damaged packaging',
        ]);

        $receive = ReturnReceive::create([
            'number' => $this->numbers->generate('return_receive'),
            'return_request_id' => $returnRequest->id,
            'received_by' => $this->dealer->id,
            'warehouse_id' => $this->warehouse->id,
            'receive_date' => $requestDate->copy()->addDay(),
        ]);

        $restockBatch = $this->inventory->receiveStock(
            productId: $product->id,
            warehouseId: $this->warehouse->id,
            batchNo: $sku.'-RET-M'.$monthIndex,
            expiryDate: $requestDate->copy()->addYear()->toDateString(),
            costPrice: (float) $product->purchase_price,
            quantity: 3,
            reference: $receive,
        );
        ReturnReceiveItem::create([
            'return_receive_id' => $receive->id,
            'product_id' => $product->id,
            'batch_id' => $restockBatch->id,
            'quantity' => 3,
            'disposition' => 'restock',
        ]);

        $retailerCredit = 3 * (float) $product->sale_price;
        $locked = Retailer::whereKey($retailer->id)->lockForUpdate()->first();
        $locked->decrement('current_due', $retailerCredit);
        $locked->ledgerEntries()->create([
            'type' => 'return',
            'reference_type' => $receive->getMorphClass(),
            'reference_id' => $receive->getKey(),
            'debit' => 0,
            'credit' => $retailerCredit,
            'balance_after' => $locked->current_due,
            'entry_date' => $requestDate->copy()->addDay(),
        ]);
        $returnRequest->update(['status' => 'received']);

        if ($isCurrentMonth) {
            $recipients = User::role(['Dealer', 'Manager'])->get()->filter(fn (User $u) => $u->wantsNotification('return_request_pending'));
            Notification::send($recipients, new ReturnRequestPending($returnRequest));
        }
    }

    /** @param array<int, \Illuminate\Support\Collection<int,SalesInvoice>> $invoicesByRetailer */
    private function seedWarranty(int $monthIndex, $monthStart, bool $isCurrentMonth, array $invoicesByRetailer): void
    {
        if ($monthIndex % 4 !== 0) {
            return;
        }

        $retailer = $this->retailers[$monthIndex % $this->retailers->count()];
        $invoice = ($invoicesByRetailer[$retailer->id] ?? collect())->first();

        if (! $invoice) {
            return;
        }

        $item = $invoice->items()->first();

        if (! $item) {
            return;
        }

        $statuses = ['replaced', 'repaired', 'rejected'];
        $status = $isCurrentMonth ? 'pending' : $statuses[intdiv($monthIndex, 4) % count($statuses)];

        $claim = WarrantyClaim::create([
            'number' => $this->numbers->generate('warranty_claim'),
            'retailer_id' => $retailer->id,
            'product_id' => $item->product_id,
            'sales_invoice_id' => $invoice->id,
            'claim_date' => $monthStart->copy()->addDays(18),
            'issue_description' => 'Product seal broken on arrival, customer requesting replacement.',
            'status' => $status,
        ]);

        if ($isCurrentMonth) {
            $recipients = User::role(['Dealer', 'Manager'])->get()->filter(fn (User $u) => $u->wantsNotification('warranty_claim_pending'));
            Notification::send($recipients, new WarrantyClaimPending($claim));
        }
    }

    /** @param array<int, \Illuminate\Support\Collection<int,SalesInvoice>> $invoicesByRetailer */
    private function seedPayroll(int $monthIndex, $monthStart, bool $isCurrentMonth, array $invoicesByRetailer): void
    {
        $period = $monthStart->format('Y-m');
        $monthEnd = $monthStart->copy()->endOfMonth();

        foreach ($this->representatives as $rep) {
            $repRetailerIds = $this->retailers->where('representative_id', $rep->id)->pluck('id');

            $deliveredAmount = (float) collect($invoicesByRetailer)
                ->only($repRetailerIds->all())
                ->flatten()
                ->sum('grand_total');

            $returnAdjustment = (float) ReturnRequestItem::whereHas('returnRequest', fn ($q) => $q->where('status', 'received')
                ->whereIn('retailer_id', $repRetailerIds)
                ->whereBetween('request_date', [$monthStart, $monthEnd]))
                ->get()
                ->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);

            $commissionBase = max(0, $deliveredAmount - $returnAdjustment);
            $commissionAmount = round($commissionBase * (float) $rep->commission_rate / 100, 2);

            Commission::create([
                'representative_id' => $rep->id,
                'sales_invoice_id' => null,
                'period' => $period,
                'delivered_amount' => $deliveredAmount,
                'commission_rate' => $rep->commission_rate,
                'commission_amount' => $commissionAmount,
                'return_adjustment' => $returnAdjustment,
            ]);

            $bonusAmount = 0.0;
            if ($monthIndex % 6 === 0) {
                $bonusAmount = 500.0;
                Bonus::create([
                    'representative_id' => $rep->id,
                    'amount' => $bonusAmount,
                    'bonus_date' => $monthStart->copy()->addDays(20),
                    'reason' => 'Best performer of the period',
                ]);
            }

            $advanceDeduction = 0.0;
            if ($monthIndex % 5 === 0) {
                $advanceDeduction = 800.0;
                Advance::create([
                    'representative_id' => $rep->id,
                    'amount' => $advanceDeduction,
                    'advance_date' => $monthStart->copy()->addDays(4),
                    'reason' => 'Travel advance',
                    'deducted' => true,
                ]);
            }

            $baseSalary = (float) $rep->salary_base;
            $netSalary = $baseSalary + $commissionAmount + $bonusAmount - $advanceDeduction;

            PayrollEntry::create([
                'number' => $this->numbers->generate('payroll_entry'),
                'representative_id' => $rep->id,
                'period' => $period,
                'base_salary' => $baseSalary,
                'commission_amount' => $commissionAmount,
                'bonus_amount' => $bonusAmount,
                'advance_deduction' => $advanceDeduction,
                'other_deduction' => 0,
                'net_salary' => $netSalary,
                'status' => $isCurrentMonth ? 'pending' : 'paid',
            ]);

            RepresentativeTarget::updateOrCreate(
                ['representative_id' => $rep->id, 'period' => $period],
                ['target_amount' => $rep->target_amount, 'achieved_amount' => $deliveredAmount]
            );
        }
    }

    /** @param array<string,int> $lastBatchBySku */
    private function seedPurchaseReturn($monthStart, array $lastBatchBySku): void
    {
        $sku = 'SAVLON-AS-100';

        if (! isset($lastBatchBySku[$sku])) {
            return;
        }

        $product = $this->products[$sku];

        $purchaseReturn = PurchaseReturn::create([
            'number' => $this->numbers->generate('purchase_return'),
            'company_id' => $this->companies['ACI'],
            'warehouse_id' => $this->warehouse->id,
            'return_date' => today(),
            'reason' => 'Received expired stock',
            'status' => 'draft',
            'total_amount' => 5 * (float) $product->purchase_price,
        ]);
        PurchaseReturnItem::create([
            'purchase_return_id' => $purchaseReturn->id,
            'product_id' => $product->id,
            'batch_id' => $lastBatchBySku[$sku],
            'quantity' => 5,
            'unit_cost' => $product->purchase_price,
        ]);
        $this->inventory->returnToSupplier($lastBatchBySku[$sku], 5, $purchaseReturn);
        $purchaseReturn->update(['status' => 'sent']);
    }
}
