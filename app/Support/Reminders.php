<?php

namespace App\Support;

use App\Models\Cheque;
use App\Models\User;
use App\Models\Settings\AppSetting;
use Illuminate\Support\Carbon;

/**
 * Things waiting for someone, shown at the top of the dashboards: cheques
 * due, last month's VAT, depreciation, dealer incentives, orders and cost
 * sheets to approve, backups. Each only for users who can act on it.
 *
 * @phpstan-type Reminder array{level: 'red'|'amber'|'blue', text: string, href: string, action: string}
 */
class Reminders
{
    /** @return array<int, array{level: string, text: string, href: string, action: string}> */
    public static function for(User $user): array
    {
        $out = [];
        $headOffice = ! $user->dealer_id;
        $prev = today()->subMonthNoOverflow()->format('Y-m');
        $money = fn ($v) => '৳ '.number_format((float) $v, 0);

        // Cheques
        if ($user->can('collection.view')) {
            $due = Cheque::whereIn('status', ['in_hand', 'deposited'])->whereDate('cheque_date', '<=', today());
            if (($n = (clone $due)->count()) > 0) {
                $out[] = ['level' => 'red', 'href' => route('collection.cheques', ['filter' => 'due']), 'action' => __('Open cheques'),
                    'text' => __(':n cheque(s) due or overdue, :a in all.', ['n' => $n, 'a' => $money((clone $due)->sum('amount'))])];
            }
            $stuck = Cheque::where('status', 'deposited')->whereDate('deposited_date', '<=', today()->subDays(5))->count();
            if ($stuck > 0) {
                $out[] = ['level' => 'amber', 'href' => route('collection.cheques'), 'action' => __('Check with the bank'),
                    'text' => __(':n deposited cheque(s) not cleared after 5 days.', ['n' => $stuck])];
            }
        }

        // Orders waiting for approval
        if ($user->can('sales.approve')) {
            $n = \App\Models\Sales\SalesOrder::whereIn('status', ['pending', 'credit_hold'])->count();
            if ($n > 0) {
                $out[] = ['level' => 'blue', 'href' => route('sales.orders.index'), 'action' => __('Review'),
                    'text' => __(':n sales order(s) waiting for approval.', ['n' => $n])];
            }
        }

        if ($headOffice) {
            // Last month's VAT
            if ($user->can('accounts.manage') && \App\Services\Vat\VatService::enabled()
                && ! \App\Models\Vat\VatReturn::where('period', $prev)->exists()) {
                $s = app(\App\Services\Vat\VatReturnService::class)->summary($prev);
                if ($s['output_balance'] > 0) {
                    $out[] = ['level' => today()->day > 15 ? 'red' : 'amber', 'href' => route('reports.vat', ['month' => $prev]), 'action' => __('Settle VAT'),
                        'text' => __('VAT for :m is not settled yet: :a to pay (the return is due on the 15th).', ['m' => Carbon::createFromFormat('Y-m', $prev)->translatedFormat('F Y'), 'a' => $money($s['payable'])])];
                }
            }

            // Depreciation
            if ($user->can('accounts.manage') && \App\Models\FixedAsset::where('status', 'active')->exists()) {
                $last = \App\Models\AssetDepreciation::max('period');
                $earliest = \App\Models\FixedAsset::where('status', 'active')->min('purchase_date');
                $needed = $earliest && Carbon::parse($earliest)->format('Y-m') <= $prev;
                if ($needed && (! $last || $last < $prev)) {
                    $out[] = ['level' => 'amber', 'href' => route('accounts.assets'), 'action' => __('Run depreciation'),
                        'text' => __('Depreciation has not been run for :m.', ['m' => $prev])];
                }
            }

            // Dealer incentives ready to post
            if ($user->can('accounts.manage') && $user->can('dealers.view')) {
                $svc = app(\App\Services\DealerTargetService::class);
                $ready = \App\Models\DealerTarget::where('period', $prev)->whereNull('posted_at')->get()
                    ->filter(fn ($t) => $svc->incentiveFor($t, $svc->achieved($t)) > 0)->count();
                if ($ready > 0) {
                    $out[] = ['level' => 'blue', 'href' => route('dealers.targets', ['month' => $prev]), 'action' => __('Post incentives'),
                        'text' => __(':n dealer(s) reached last month\'s target; their incentive is not posted yet.', ['n' => $ready])];
                }
            }

            // Cost sheets to approve
            if ($user->can('costing.approve') && Modules::enabled('costing')) {
                $n = \App\Models\Costing\CostSheet::where('status', 'draft')->count();
                if ($n > 0) {
                    $out[] = ['level' => 'blue', 'href' => route('costing.index'), 'action' => __('Review'),
                        'text' => __(':n cost sheet(s) waiting for approval.', ['n' => $n])];
                }
            }

            // Setup and backups
            if ($user->can('settings.manage')) {
                if (\App\Services\Vat\VatService::enabled() && in_array(\App\Services\Vat\VatService::bin(), [null, '', '000000000-0000'], true)) {
                    $out[] = ['level' => 'red', 'href' => route('settings.company-profile'), 'action' => __('Enter BIN'),
                        'text' => __('VAT is on but the company BIN is missing or still the demo one; VAT invoices show it.')];
                }
                if (\App\Services\OffsiteBackupService::method() === 'none') {
                    $out[] = ['level' => 'amber', 'href' => route('more.backup-restore'), 'action' => __('Set up'),
                        'text' => __('Backups stay on this server only. Set up an off-site copy (email or FTP).')];
                } elseif (AppSetting::get('offsite_last_at') && ! AppSetting::flag('offsite_last_ok')) {
                    $out[] = ['level' => 'red', 'href' => route('more.backup-restore'), 'action' => __('Fix'),
                        'text' => __('The last off-site backup copy failed: :m', ['m' => (string) AppSetting::get('offsite_last_message')])];
                }
            }
        }

        $order = ['red' => 0, 'amber' => 1, 'blue' => 2];
        usort($out, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);

        return $out;
    }
}
