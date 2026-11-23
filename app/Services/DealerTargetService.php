<?php

namespace App\Services;

use App\Models\DealerTarget;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Services\Accounts\LedgerService;
use App\Support\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly purchase targets for dealers. What a dealer bought from the head
 * office in the month (its invoices) is measured against the target; when it
 * is reached the incentive (a % of the purchases) is credited to the
 * dealer's account, which lowers what it owes:
 *
 *   head office:  Dr Dealer Incentives   Cr Receivable (the dealer)
 *   dealer books: Dr Payable (head office) Cr Other Income
 */
class DealerTargetService
{
    public function __construct(private LedgerService $ledger) {}

    /** The dealer's purchases (invoice totals) in the target's month. */
    public function achieved(DealerTarget $t): float
    {
        $account = $this->account($t);
        if (! $account) {
            return 0.0;
        }
        $start = Carbon::createFromFormat('Y-m-d', $t->period.'-01');

        return round((float) Tenant::bypass(fn () => SalesInvoice::where('retailer_id', $account->id)
            ->whereBetween('invoice_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->sum('grand_total')), 2);
    }

    public function incentiveFor(DealerTarget $t, float $achieved): float
    {
        return $achieved >= (float) $t->target_amount && (float) $t->target_amount > 0
            ? round($achieved * (float) $t->incentive_percent / 100, 2)
            : 0.0;
    }

    public function post(DealerTarget $t): void
    {
        if ($t->posted_at) {
            throw new \RuntimeException(__('The incentive for this month is already posted.'));
        }
        $end = Carbon::createFromFormat('Y-m-d', $t->period.'-01')->endOfMonth();
        if ($end->isFuture()) {
            throw new \RuntimeException(__('The month is not over yet.'));
        }
        $achieved = $this->achieved($t);
        $amount = $this->incentiveFor($t, $achieved);
        if ($amount <= 0) {
            throw new \RuntimeException(__('The target was not reached, so there is no incentive.'));
        }
        $account = $this->account($t) ?? throw new \RuntimeException(__('This dealer has no account with the head office.'));

        DB::transaction(function () use ($t, $achieved, $amount, $account) {
            $date = today()->toDateString();
            $entry = $this->ledger->post([
                'type' => 'journal',
                'entry_date' => $date,
                'dealer_id' => null,
                'narration' => __('Target incentive :p, :d', ['p' => $t->period, 'd' => $t->dealer->name]),
                'source' => $t,
                'source_event' => 'dealer_incentive',
            ], [
                ['account' => 'dealer_incentive', 'debit' => $amount],
                ['account' => 'ar', 'credit' => $amount, 'party_type' => 'retailer', 'party_id' => $account->id],
            ]);

            $this->ledger->post([
                'type' => 'journal',
                'entry_date' => $date,
                'dealer_id' => $t->dealer_id,
                'narration' => __('Target incentive from Agro Tech, :p', ['p' => $t->period]),
                'source' => $t,
                'source_event' => 'dealer_incentive_received',
            ], [
                ['account' => 'ap', 'debit' => $amount, 'party_type' => 'head_office'],
                ['account' => 'other_income', 'credit' => $amount],
            ]);

            $retailer = Tenant::bypass(fn () => Retailer::whereKey($account->id)->lockForUpdate()->first());
            $retailer->decrement('current_due', $amount);
            $retailer->ledgerEntries()->create([
                'type' => 'incentive',
                'reference_type' => $t->getMorphClass(),
                'reference_id' => $t->getKey(),
                'debit' => 0,
                'credit' => $amount,
                'balance_after' => $retailer->current_due,
                'entry_date' => $date,
            ]);

            $t->update(['achieved_amount' => $achieved, 'incentive_amount' => $amount, 'journal_entry_id' => $entry->id, 'posted_at' => now()]);
        });
    }

    private function account(DealerTarget $t): ?Retailer
    {
        return Tenant::bypass(fn () => Retailer::where('account_dealer_id', $t->dealer_id)->first());
    }
}
