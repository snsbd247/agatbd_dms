<?php

namespace App\Services\Hr;

use App\Models\Payroll\Commission;
use App\Models\Payroll\RepresentativeTarget;
use App\Models\Representative;
use App\Models\Retailer;
use App\Models\Returns\ReturnRequestItem;
use App\Models\Sales\SalesInvoice;
use Carbon\Carbon;

/**
 * A marketing representative's commission for a month: their rate on what
 * was invoiced through their orders, less goods their customers returned in
 * the month. Recorded once per month (recomputing updates it) together with
 * the month's target achievement.
 */
class CommissionService
{
    public function forPeriod(Representative $rep, string $period): Commission
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $delivered = (float) SalesInvoice::whereHas('salesOrder', fn ($q) => $q->where('representative_id', $rep->id))
            ->whereBetween('invoice_date', [$start, $end])
            ->sum('grand_total');

        $retailerIds = Retailer::where('representative_id', $rep->id)->pluck('id');
        $returns = (float) ReturnRequestItem::whereHas('returnRequest', fn ($q) => $q->where('status', 'received')
            ->whereIn('retailer_id', $retailerIds)
            ->whereBetween('request_date', [$start, $end]))
            ->get()
            ->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);

        $amount = round(max(0, $delivered - $returns) * (float) $rep->commission_rate / 100, 2);

        RepresentativeTarget::updateOrCreate(
            ['representative_id' => $rep->id, 'period' => $period],
            ['target_amount' => $rep->target_amount, 'achieved_amount' => $delivered]
        );

        return Commission::updateOrCreate(
            ['representative_id' => $rep->id, 'period' => $period, 'sales_invoice_id' => null],
            [
                'delivered_amount' => $delivered,
                'commission_rate' => $rep->commission_rate,
                'commission_amount' => $amount,
                'return_adjustment' => $returns,
            ]
        );
    }
}
