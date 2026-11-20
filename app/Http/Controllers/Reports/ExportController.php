<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Batch;
use App\Models\Company;
use App\Models\PaymentCollection;
use App\Models\Payroll\Commission;
use App\Models\Payroll\PayrollEntry;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Representative;
use App\Models\Retailer;
use App\Models\Returns\ReturnRequest;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceItem;
use App\Models\Stock as StockModel;
use App\Models\Warranty\WarrantyClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExportController extends Controller
{
    public function sales(Request $request)
    {
        $from = $request->query('from') ?: today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();

        $query = SalesInvoice::query()
            ->with(['retailer', 'salesOrder.representative'])
            ->whereBetween('invoice_date', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->when($request->query('retailer_id'), fn ($q) => $q->where('retailer_id', $request->query('retailer_id')))
            ->when($request->query('representative_id'), fn ($q) => $q->whereHas('salesOrder', fn ($sq) => $sq->where('representative_id', $request->query('representative_id'))))
            ->latest('invoice_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice #', 'Date', 'Retailer', 'Representative', 'Amount', 'Status']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $invoice) {
                    fputcsv($out, [
                        $invoice->number,
                        $invoice->invoice_date->toDateString(),
                        $invoice->retailer?->name,
                        $invoice->salesOrder?->representative?->name,
                        $invoice->grand_total,
                        $invoice->status,
                    ]);
                }
            });
            fclose($out);
        }, "sales-report-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function stock(Request $request)
    {
        $warehouseId = $request->query('warehouse_id');

        $query = StockModel::query()
            ->with(['product', 'warehouse'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('quantity');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product', 'Warehouse', 'Quantity', 'Reorder Level', 'Status']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $stock) {
                    $status = $stock->quantity <= 0 ? 'Out of Stock' : ($stock->quantity <= $stock->reorder_level ? 'Low Stock' : 'OK');
                    fputcsv($out, [
                        $stock->product?->name,
                        $stock->warehouse?->name,
                        $stock->quantity,
                        $stock->reorder_level,
                        $status,
                    ]);
                }
            });
            fclose($out);
        }, 'stock-report-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function collections(Request $request)
    {
        $from = $request->query('from') ?: today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();

        $query = PaymentCollection::query()
            ->with('retailer')
            ->whereBetween('collection_date', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->latest('collection_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Number', 'Date', 'Retailer', 'Method', 'Amount']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $collection) {
                    fputcsv($out, [
                        $collection->number,
                        $collection->collection_date->toDateString(),
                        $collection->retailer?->name,
                        $collection->method,
                        $collection->amount,
                    ]);
                }
            });
            fclose($out);
        }, "collections-report-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function returns(Request $request)
    {
        $from = $request->query('from') ?: today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();
        $status = $request->query('status');

        $query = ReturnRequest::query()
            ->with(['retailer', 'items'])
            ->whereBetween('request_date', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('request_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Number', 'Date', 'Retailer', 'Value', 'Status']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $request) {
                    $value = $request->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);
                    fputcsv($out, [
                        $request->number,
                        $request->request_date->toDateString(),
                        $request->retailer?->name,
                        $value,
                        $request->status,
                    ]);
                }
            });
            fclose($out);
        }, "returns-report-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function representatives(Request $request)
    {
        $period = $request->query('period') ?: today()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $commissions = Commission::where('period', $period)->get()->keyBy('representative_id');

        $reps = Representative::where('active', true)->get();

        return response()->streamDownload(function () use ($reps, $start, $end, $commissions) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Representative', 'Delivered Sales', 'Collection', 'Target', 'Achievement %', 'Commission Earned']);
            foreach ($reps as $rep) {
                $sales = (float) SalesInvoice::whereHas('salesOrder', fn ($q) => $q->where('representative_id', $rep->id))
                    ->whereBetween('invoice_date', [$start, $end])
                    ->sum('grand_total');
                $collection = $rep->user_id
                    ? (float) PaymentCollection::where('collected_by', $rep->user_id)->whereBetween('collection_date', [$start, $end])->sum('amount')
                    : 0.0;
                $target = (float) $rep->target_amount;
                $achievement = $target > 0 ? min(100, round(($sales / $target) * 100, 1)) : 0;
                $commission = $commissions->get($rep->id);

                fputcsv($out, [
                    $rep->name,
                    $sales,
                    $collection,
                    $target,
                    $achievement,
                    $commission?->commission_amount ?? '',
                ]);
            }
            fclose($out);
        }, "representative-performance-{$period}.csv", ['Content-Type' => 'text/csv']);
    }

    public function purchase(Request $request)
    {
        $from = $request->query('from') ?: today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();

        $query = PurchaseOrder::query()
            ->with(['company', 'warehouse'])
            ->whereBetween('order_date', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->when($request->query('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->query('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('order_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Number', 'Date', 'Company', 'Warehouse', 'Total', 'Status']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $order) {
                    fputcsv($out, [
                        $order->number,
                        $order->order_date->toDateString(),
                        $order->company?->name,
                        $order->warehouse?->name,
                        $order->total_amount,
                        $order->status,
                    ]);
                }
            });
            fclose($out);
        }, "purchase-report-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function companies(Request $request)
    {
        $from = $request->query('from') ?: today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $companies = Company::orderBy('name')->get();

        return response()->streamDownload(function () use ($companies, $start, $end) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Company', 'Purchase Orders', 'Purchase Value', 'Returns']);
            foreach ($companies as $company) {
                fputcsv($out, [
                    $company->name,
                    $company->purchaseOrders()->whereBetween('order_date', [$start, $end])->count(),
                    $company->purchaseOrders()->whereBetween('order_date', [$start, $end])->sum('total_amount'),
                    $company->purchaseReturns()->whereBetween('return_date', [$start, $end])->count(),
                ]);
            }
            fclose($out);
        }, "company-report-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function products(Request $request)
    {
        $from = $request->query('from') ?: today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $items = SalesInvoiceItem::query()
            ->whereHas('salesInvoice', fn ($q) => $q->whereBetween('invoice_date', [$start, $end]))
            ->with(['product', 'batch'])
            ->get();

        $rows = $items->groupBy('product_id')->map(function ($group) {
            $profit = $group->sum(function (SalesInvoiceItem $item) {
                $cost = $item->batch?->cost_price ?? $item->product?->purchase_price ?? 0;

                return (float) $item->line_total - ((float) $cost * (float) $item->quantity);
            });

            return [
                'product' => $group->first()->product,
                'quantity' => (float) $group->sum('quantity'),
                'revenue' => (float) $group->sum('line_total'),
                'profit' => $profit,
            ];
        })->filter(fn ($row) => $row['product'] !== null)->sortByDesc('revenue')->values();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product', 'Quantity Sold', 'Revenue', 'Profit']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['product']->name,
                    $row['quantity'],
                    $row['revenue'],
                    $row['profit'],
                ]);
            }
            fclose($out);
        }, "product-performance-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function retailers(Request $request)
    {
        $query = Retailer::query()
            ->when($request->query('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
            ->when($request->query('area_id'), fn ($q) => $q->where('area_id', $request->query('area_id')))
            ->withCount('salesOrders')
            ->withSum('invoices', 'grand_total')
            ->withSum('collections', 'amount')
            ->orderByDesc('current_due');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Retailer', 'Orders', 'Total Invoiced', 'Total Paid', 'Current Due']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $retailer) {
                    fputcsv($out, [
                        $retailer->name,
                        $retailer->sales_orders_count,
                        $retailer->invoices_sum_grand_total ?? 0,
                        $retailer->collections_sum_amount ?? 0,
                        $retailer->current_due,
                    ]);
                }
            });
            fclose($out);
        }, 'retailer-report-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function warranty(Request $request)
    {
        $from = $request->query('from') ?: today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();

        $query = WarrantyClaim::query()
            ->with(['retailer', 'product'])
            ->whereBetween('claim_date', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->when($request->query('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('claim_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Number', 'Date', 'Retailer', 'Product', 'Status']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $claim) {
                    fputcsv($out, [
                        $claim->number,
                        $claim->claim_date->toDateString(),
                        $claim->retailer?->name,
                        $claim->product?->name,
                        $claim->status,
                    ]);
                }
            });
            fclose($out);
        }, "warranty-report-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function payroll(Request $request)
    {
        $fromPeriod = $request->query('from_period') ?: today()->startOfMonth()->format('Y-m');
        $toPeriod = $request->query('to_period') ?: today()->format('Y-m');

        $query = PayrollEntry::query()
            ->with('representative')
            ->whereBetween('period', [$fromPeriod, $toPeriod])
            ->when($request->query('representative_id'), fn ($q) => $q->where('representative_id', $request->query('representative_id')))
            ->latest('period');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Representative', 'Period', 'Base', 'Commission', 'Bonus', 'Deductions', 'Net Salary', 'Status']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $entry) {
                    fputcsv($out, [
                        $entry->representative?->name,
                        $entry->period,
                        $entry->base_salary,
                        $entry->commission_amount,
                        $entry->bonus_amount,
                        $entry->advance_deduction + $entry->other_deduction,
                        $entry->net_salary,
                        $entry->status,
                    ]);
                }
            });
            fclose($out);
        }, "payroll-report-{$fromPeriod}-to-{$toPeriod}.csv", ['Content-Type' => 'text/csv']);
    }

    public function profit(Request $request)
    {
        $from = $request->query('from') ?: today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $months = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $monthStart = $cursor->copy()->max($start);
            $monthEnd = $cursor->copy()->endOfMonth()->min($end);

            $revenue = (float) SalesInvoice::whereBetween('invoice_date', [$monthStart, $monthEnd])->sum('grand_total');
            $cogs = (float) SalesInvoiceItem::whereHas('salesInvoice', fn ($q) => $q->whereBetween('invoice_date', [$monthStart, $monthEnd]))
                ->with(['batch', 'product'])
                ->get()
                ->sum(function (SalesInvoiceItem $item) {
                    $cost = $item->batch?->cost_price ?? $item->product?->purchase_price ?? 0;

                    return (float) $cost * (float) $item->quantity;
                });
            $payroll = (float) PayrollEntry::whereBetween('paid_at', [$monthStart, $monthEnd])->sum('net_salary');

            $months[] = [
                $cursor->format('M Y'),
                $revenue,
                $cogs,
                $revenue - $cogs,
                $payroll,
                ($revenue - $cogs) - $payroll,
            ];

            $cursor->addMonth();
        }

        return response()->streamDownload(function () use ($months) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Month', 'Revenue', 'COGS', 'Gross Profit', 'Payroll Cost', 'Net Profit']);
            foreach ($months as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "profit-loss-report-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function expiry(Request $request)
    {
        $withinDays = $request->query('within_days');

        $query = Batch::query()
            ->with(['product', 'warehouse'])
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expiry_date')
            ->when($request->query('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($withinDays, fn ($q) => $q->where('expiry_date', '<=', today()->addDays((int) $withinDays)))
            ->orderBy('expiry_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product', 'Batch', 'Warehouse', 'Quantity', 'Expiry Date', 'Days Remaining']);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $batch) {
                    fputcsv($out, [
                        $batch->product?->name,
                        $batch->batch_no,
                        $batch->warehouse?->name,
                        $batch->quantity_available,
                        $batch->expiry_date->toDateString(),
                        today()->diffInDays($batch->expiry_date, false),
                    ]);
                }
            });
            fclose($out);
        }, 'expiry-report-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function areas(Request $request)
    {
        $from = $request->query('from') ?: today()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $areas = Area::withCount(['retailers', 'representatives'])->orderBy('name')->get();

        return response()->streamDownload(function () use ($areas, $start, $end) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Area', 'Retailers', 'Representatives', 'Sales', 'Due']);
            foreach ($areas as $area) {
                $sales = SalesInvoice::whereHas('retailer', fn ($q) => $q->where('area_id', $area->id))
                    ->whereBetween('invoice_date', [$start, $end])
                    ->sum('grand_total');
                $due = Retailer::where('area_id', $area->id)->sum('current_due');

                fputcsv($out, [
                    $area->name,
                    $area->retailers_count,
                    $area->representatives_count,
                    $sales,
                    $due,
                ]);
            }
            fclose($out);
        }, "area-report-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }
}
