<?php

namespace App\Services;

use App\Models\AssetDepreciation;
use App\Models\FixedAsset;
use App\Services\Accounts\LedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fixed assets of the head office.
 *
 *  - Adding: Dr the asset account, Cr bank / cash / the supplier (or
 *    nothing when it was already in the opening balances).
 *  - Depreciation, month by month from the purchase month:
 *      straight line     (cost − salvage) ÷ useful life in months
 *      reducing balance  book value × yearly rate ÷ 12
 *    never going below the salvage value. One voucher per month for all
 *    assets: Dr Depreciation, Cr Accumulated Depreciation.
 *  - Disposal: the cost and its accumulated depreciation leave the books;
 *    the difference against the sale price is a gain or loss.
 */
class FixedAssetService
{
    public function __construct(private LedgerService $ledger, private NumberGeneratorService $numbers) {}

    public function add(array $data): FixedAsset
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = ($data['code'] ?? '') ?: $this->numbers->generate('fixed_asset');
            $asset = FixedAsset::create($data + ['status' => 'active', 'accumulated_depreciation' => 0]);

            $credit = match ($asset->funded_by) {
                'bank' => ['account' => 'bank'],
                'cash' => ['account' => 'cash'],
                'supplier' => ['account' => 'ap', 'party_type' => 'company', 'party_id' => $asset->company_id],
                default => null,   // opening: already in the opening balances
            };
            if ($credit) {
                $this->ledger->post([
                    'type' => 'journal',
                    'entry_date' => $asset->purchase_date->toDateString(),
                    'narration' => __('Fixed asset bought: :n', ['n' => $asset->name]),
                    'source' => $asset,
                    'source_event' => 'asset_purchase',
                ], [
                    ['account' => (int) $asset->account_id, 'debit' => (float) $asset->cost],
                    $credit + ['credit' => (float) $asset->cost],
                ]);
            }

            return $asset;
        });
    }

    /** One month's charge for an asset, given what is already depreciated. */
    public function monthlyCharge(FixedAsset $a, float $accumulated): float
    {
        $room = round((float) $a->cost - (float) $a->salvage_value - $accumulated, 2);
        if ($room <= 0) {
            return 0.0;
        }
        $charge = $a->method === 'reducing_balance'
            ? ((float) $a->cost - $accumulated) * (float) $a->rate_percent / 100 / 12
            : ((float) $a->cost - (float) $a->salvage_value) / max(1, (int) $a->useful_life_months);

        return round(min($charge, $room), 2);
    }

    /**
     * Depreciate every active asset for every month not yet done, up to and
     * including $upTo (YYYY-MM). One voucher per month, dated its last day.
     *
     * @return array<string, float> month => total posted
     */
    public function run(string $upTo): array
    {
        $end = Carbon::createFromFormat('Y-m-d', $upTo.'-01')->endOfMonth();
        if ($end->isAfter(today()->endOfMonth())) {
            throw new \RuntimeException(__('Depreciation cannot be run for a future month.'));
        }

        /** @var array<string, array<int, float>> month => asset id => amount */
        $plan = [];
        $assets = FixedAsset::where('status', 'active')->whereDate('purchase_date', '<=', $end)->get();
        foreach ($assets as $a) {
            $done = $a->depreciations()->pluck('period')->all();
            $acc = (float) $a->accumulated_depreciation;
            for ($m = $a->purchase_date->copy()->startOfMonth(); $m->lte($end); $m->addMonth()) {
                $p = $m->format('Y-m');
                if (in_array($p, $done, true)) {
                    continue;
                }
                $charge = $this->monthlyCharge($a, $acc);
                if ($charge <= 0) {
                    break;
                }
                $plan[$p][$a->id] = $charge;
                $acc += $charge;
            }
        }
        ksort($plan);

        $posted = [];
        DB::transaction(function () use ($plan, $assets, &$posted) {
            $byId = $assets->keyBy('id');
            foreach ($plan as $period => $charges) {
                $total = round(array_sum($charges), 2);
                $entry = $this->ledger->post([
                    'type' => 'journal',
                    'entry_date' => Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth()->toDateString(),
                    'narration' => __('Depreciation :p (:n assets)', ['p' => $period, 'n' => count($charges)]),
                    'source_event' => 'depreciation',
                ], [
                    ['account' => 'depreciation', 'debit' => $total],
                    ['account' => 'accum_depreciation', 'credit' => $total],
                ]);
                foreach ($charges as $id => $amount) {
                    AssetDepreciation::create(['fixed_asset_id' => $id, 'period' => $period, 'amount' => $amount, 'journal_entry_id' => $entry->id]);
                    $byId[$id]->increment('accumulated_depreciation', $amount);
                }
                $posted[$period] = $total;
            }
        });

        return $posted;
    }

    /** Sell or scrap an asset. Depreciation must be run up to the month before. */
    public function dispose(FixedAsset $a, string $date, float $proceeds, string $into): void
    {
        if ($a->status !== 'active') {
            throw new \RuntimeException(__('This asset is already disposed of.'));
        }
        $prev = Carbon::parse($date)->subMonth()->format('Y-m');
        $prevEnd = Carbon::parse($date)->subMonth()->endOfMonth();
        if ($a->purchase_date->lte($prevEnd) && $this->monthlyCharge($a, (float) $a->accumulated_depreciation) > 0
            && ! $a->depreciations()->where('period', $prev)->exists()) {
            throw new \RuntimeException(__('Run depreciation up to :m first.', ['m' => $prev]));
        }

        DB::transaction(function () use ($a, $date, $proceeds, $into) {
            $cost = round((float) $a->cost, 2);
            $acc = round((float) $a->accumulated_depreciation, 2);
            $proceeds = round(max(0, $proceeds), 2);
            $gain = round($proceeds - ($cost - $acc), 2);

            $this->ledger->post([
                'type' => 'journal',
                'entry_date' => $date,
                'narration' => __('Fixed asset disposed: :n', ['n' => $a->name]),
                'source' => $a,
                'source_event' => 'asset_disposal',
            ], array_values(array_filter([
                $proceeds > 0 ? ['account' => $into === 'cash' ? 'cash' : 'bank', 'debit' => $proceeds] : null,
                $acc > 0 ? ['account' => 'accum_depreciation', 'debit' => $acc] : null,
                ['account' => (int) $a->account_id, 'credit' => $cost],
                $gain > 0 ? ['account' => 'other_income', 'credit' => $gain] : null,
                $gain < 0 ? ['account' => 'other_income', 'debit' => -$gain] : null,
            ])));

            $a->update(['status' => 'disposed', 'disposed_date' => $date, 'disposal_amount' => $proceeds]);
        });
    }
}
