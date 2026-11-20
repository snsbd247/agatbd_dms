<?php

namespace App\Livewire\Dashboard;

use App\Models\Batch;
use App\Models\Company;
use App\Models\Delivery\DeliveryNote;
use App\Models\PaymentCollection;
use App\Models\Product;
use App\Models\Purchase\GoodsReceipt;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Representative;
use App\Models\Retailer;
use App\Models\Returns\ReturnRequest;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceItem;
use App\Models\Sales\SalesOrder;
use App\Models\Stock;
use App\Models\Warranty\WarrantyClaim;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dealer Dashboard')]
class Dealer extends Component
{
    public string $rangeFrom;
    public string $rangeTo;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('Dealer') || auth()->user()->hasRole('Manager'), 403);

        $this->rangeFrom = today()->toDateString();
        $this->rangeTo = today()->toDateString();
    }

    public function render()
    {
        $from = Carbon::parse($this->rangeFrom)->startOfDay();
        $to = Carbon::parse($this->rangeTo)->endOfDay();
        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();

        $stats = [
            'range_sales' => SalesInvoice::whereBetween('invoice_date', [$from, $to])->sum('grand_total'),
            'range_collection' => PaymentCollection::whereBetween('collection_date', [$from, $to])->sum('amount'),
            'total_due' => Retailer::sum('current_due'),
            'monthly_revenue' => SalesInvoice::whereBetween('invoice_date', [$monthStart, $monthEnd])->sum('grand_total'),
            'monthly_profit' => $this->monthlyProfit($monthStart, $monthEnd),
            'stock_value' => (float) Batch::selectRaw('COALESCE(SUM(quantity_available * cost_price), 0) as total')->value('total'),
            'pending_purchase_value' => (float) PurchaseOrder::whereIn('status', ['sent', 'partial'])->sum('total_amount'),
            'retailer_count' => Retailer::count(),
            'company_count' => Company::count(),
            'purchase_receipt_count' => GoodsReceipt::count(),
            'sales_invoice_count' => SalesInvoice::count(),
        ];

        $trends = $this->dayOverDayTrends($from, $to, (float) $stats['range_sales'], (float) $stats['range_collection']);

        $orderCounts = [
            'pending_orders' => SalesOrder::where('status', 'pending')->count(),
            'pending_deliveries' => SalesOrder::whereIn('status', ['approved', 'partial'])->count(),
            'pending_returns' => ReturnRequest::where('status', 'pending')->count(),
            'warranty_claims' => WarrantyClaim::where('status', 'pending')->count(),
        ];

        $alerts = [
            'low_stock' => Stock::whereColumn('quantity', '<=', 'reorder_level')->where('quantity', '>', 0)->distinct()->count('product_id'),
            'out_of_stock' => Stock::where('quantity', '<=', 0)->distinct()->count('product_id'),
            'near_expiry' => Batch::where('quantity_available', '>', 0)->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->addDays(30)])->distinct()->count('product_id'),
            'overdue_retailers' => Retailer::where('current_due', '>', 0)->count(),
        ];

        $topProducts = SalesInvoiceItem::query()
            ->whereHas('salesInvoice', fn ($q) => $q->whereBetween('invoice_date', [$monthStart, $monthEnd]))
            ->selectRaw('product_id, SUM(quantity) as total_qty')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->with('product')
            ->limit(5)
            ->get();

        $topRetailers = SalesInvoice::query()
            ->whereBetween('invoice_date', [$monthStart, $monthEnd])
            ->selectRaw('retailer_id, SUM(grand_total) as total_sales')
            ->groupBy('retailer_id')
            ->orderByDesc('total_sales')
            ->with('retailer')
            ->limit(5)
            ->get();

        $repPerformance = Representative::query()->where('active', true)->get()->map(function (Representative $rep) use ($monthStart, $monthEnd) {
            $sales = SalesInvoice::whereHas('salesOrder', fn ($q) => $q->where('representative_id', $rep->id))
                ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                ->sum('grand_total');
            $collection = $rep->user_id
                ? PaymentCollection::where('collected_by', $rep->user_id)->whereBetween('collection_date', [$monthStart, $monthEnd])->sum('amount')
                : 0;
            $target = (float) $rep->target_amount;

            return [
                'representative' => $rep,
                'sales' => (float) $sales,
                'collection' => (float) $collection,
                'target' => $target,
                'achievement' => $target > 0 ? min(100, round(((float) $sales / $target) * 100)) : 0,
            ];
        })->sortByDesc('sales')->take(5)->values();

        $collectionVsSales = [
            'sales' => (float) $stats['monthly_revenue'],
            'collection' => (float) PaymentCollection::whereBetween('collection_date', [$monthStart, $monthEnd])->sum('amount'),
        ];
        $collectionVsSales['rate'] = $collectionVsSales['sales'] > 0
            ? round(($collectionVsSales['collection'] / $collectionVsSales['sales']) * 100, 1)
            : 0;

        $recentOrders = SalesOrder::with('retailer')->latest('order_date')->latest('id')->limit(5)->get();

        $recentDeliveries = DeliveryNote::with(['salesOrder.retailer', 'items.salesOrderItem'])
            ->latest('delivery_date')->latest('id')->limit(5)->get()
            ->map(function (DeliveryNote $note) {
                $amount = $note->items->sum(fn ($item) => (float) $item->quantity * (float) ($item->salesOrderItem->unit_price ?? 0));

                return ['note' => $note, 'amount' => $amount];
            });

        $salesChart = $this->dailySalesAndCollection($monthStart, min($monthEnd, today()));
        $purchaseSalesChart = $this->monthlyPurchaseAndSales();
        $recentProducts = Product::with('category')->latest()->limit(5)->get();
        $expiringBatches = Batch::with(['product.category', 'warehouse'])
            ->where('quantity_available', '>', 0)->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today()->addDays(30))
            ->orderBy('expiry_date')->limit(6)->get();
        $lowStockItems = Stock::with(['product.category', 'warehouse'])
            ->whereColumn('quantity', '<=', 'reorder_level')->orderBy('quantity')->limit(8)->get();

        $recentActivities = $this->recentActivities();

        return view('livewire.dashboard.dealer', [
            'stats' => $stats,
            'trends' => $trends,
            'orderCounts' => $orderCounts,
            'alerts' => $alerts,
            'topProducts' => $topProducts,
            'topRetailers' => $topRetailers,
            'repPerformance' => $repPerformance,
            'collectionVsSales' => $collectionVsSales,
            'recentOrders' => $recentOrders,
            'recentDeliveries' => $recentDeliveries,
            'salesChart' => $salesChart,
            'purchaseSalesChart' => $purchaseSalesChart,
            'recentProducts' => $recentProducts,
            'expiringBatches' => $expiringBatches,
            'lowStockItems' => $lowStockItems,
            'recentActivities' => $recentActivities,
        ]);
    }

    private function dayOverDayTrends(Carbon $from, Carbon $to, float $rangeSales, float $rangeCollection): array
    {
        if (! $from->isSameDay($to)) {
            return ['sales_pct' => null, 'collection_pct' => null];
        }

        $prevFrom = $from->copy()->subDay()->startOfDay();
        $prevTo = $from->copy()->subDay()->endOfDay();

        $prevSales = (float) SalesInvoice::whereBetween('invoice_date', [$prevFrom, $prevTo])->sum('grand_total');
        $prevCollection = (float) PaymentCollection::whereBetween('collection_date', [$prevFrom, $prevTo])->sum('amount');

        return [
            'sales_pct' => $prevSales > 0 ? round((($rangeSales - $prevSales) / $prevSales) * 100, 1) : null,
            'collection_pct' => $prevCollection > 0 ? round((($rangeCollection - $prevCollection) / $prevCollection) * 100, 1) : null,
        ];
    }

    private function monthlyProfit(Carbon $start, Carbon $end): float
    {
        $items = SalesInvoiceItem::whereHas('salesInvoice', fn ($q) => $q->whereBetween('invoice_date', [$start, $end]))
            ->with(['batch', 'product'])
            ->get();

        return (float) $items->sum(function (SalesInvoiceItem $item) {
            $cost = $item->batch?->cost_price ?? $item->product?->purchase_price ?? 0;

            return (float) $item->line_total - ((float) $cost * (float) $item->quantity);
        });
    }

    private function dailySalesAndCollection(Carbon $start, Carbon $end): array
    {
        $invoices = SalesInvoice::whereBetween('invoice_date', [$start, $end])->get(['invoice_date', 'grand_total']);
        $collections = PaymentCollection::whereBetween('collection_date', [$start, $end])->get(['collection_date', 'amount']);

        $labels = [];
        $sales = [];
        $collectionTotals = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('j M');
            $sales[] = (float) $invoices->filter(fn ($i) => $i->invoice_date->toDateString() === $key)->sum('grand_total');
            $collectionTotals[] = (float) $collections->filter(fn ($c) => $c->collection_date->toDateString() === $key)->sum('amount');
        }

        return ['labels' => $labels, 'sales' => $sales, 'collection' => $collectionTotals];
    }

    private function monthlyPurchaseAndSales(): array
    {
        $start = today()->startOfYear();
        $end = today()->endOfYear();
        $purchases = GoodsReceipt::whereBetween('receipt_date', [$start, $end])->get(['receipt_date', 'total_amount']);
        $sales = SalesInvoice::whereBetween('invoice_date', [$start, $end])->get(['invoice_date', 'grand_total']);

        $labels = [];
        $purchaseTotals = [];
        $salesTotals = [];
        for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
            $labels[] = $month->format('M');
            $purchaseTotals[] = (float) $purchases->filter(fn ($row) => $row->receipt_date->format('Y-m') === $month->format('Y-m'))->sum('total_amount');
            $salesTotals[] = (float) $sales->filter(fn ($row) => $row->invoice_date->format('Y-m') === $month->format('Y-m'))->sum('grand_total');
        }

        return ['labels' => $labels, 'purchases' => $purchaseTotals, 'sales' => $salesTotals];
    }

    private function recentActivities(): array
    {
        $orders = SalesOrder::with('retailer')->latest('created_at')->limit(5)->get()
            ->map(fn ($o) => ['at' => $o->created_at, 'message' => "New Order {$o->number} created for {$o->retailer?->name}"]);

        $deliveries = DeliveryNote::with('salesOrder.retailer')->latest('created_at')->limit(5)->get()
            ->map(fn ($d) => ['at' => $d->created_at, 'message' => "Delivery {$d->number} completed for {$d->salesOrder?->retailer?->name}"]);

        $collections = PaymentCollection::with('retailer')->latest('created_at')->limit(5)->get()
            ->map(fn ($c) => ['at' => $c->created_at, 'message' => 'Payment ৳'.number_format($c->amount, 2)." collected from {$c->retailer?->name}"]);

        return $orders->concat($deliveries)->concat($collections)
            ->sortByDesc('at')
            ->take(8)
            ->values()
            ->all();
    }
}
