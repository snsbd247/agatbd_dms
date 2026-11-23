<?php

namespace Database\Seeders;

use App\Models\Accounts\Account;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\DealerTarget;
use App\Models\FixedAsset;
use App\Models\Import\ImportShipment;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\Scheme;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vat\VatReturn;
use App\Services\ChequeService;
use App\Services\DealerTargetService;
use App\Services\FixedAssetService;
use App\Services\Import\ImportService;
use App\Services\NumberGeneratorService;
use App\Services\SchemeService;
use App\Services\Vat\VatReturnService;
use App\Services\Vat\VatService;
use App\Services\Vat\VdsService;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

/**
 * Demo data for the features added after the year of ERP demo data: trade
 * schemes (with two orders that use them), dealer targets and incentives,
 * customers' cheques in every state, fixed assets with depreciation, VAT
 * returns settled month by month with VDS both ways, import payments with
 * an exchange difference, and two custom roles.
 *
 * Runs on top of ErpDemoSeeder and leaves the latest month's VAT,
 * depreciation and incentives open, so the dashboard reminders have
 * something to show. Skips itself when it has already run.
 *
 *   php artisan db:seed --class=ErpDemoExtrasSeeder
 */
class ErpDemoExtrasSeeder extends Seeder
{
    private User $admin;

    public function run(): void
    {
        if (! Product::where('sku', 'AGT-NPK50')->exists()) {
            $this->command?->warn('Run ErpDemoSeeder first.');

            return;
        }
        if (Scheme::where('name', 'like', 'Demo:%')->exists()) {
            $this->command?->info('Demo extras already present, skipping.');

            return;
        }

        $this->admin = User::role('Admin')->whereNull('dealer_id')->orderBy('id')->firstOrFail();
        Auth::setUser($this->admin);
        $realNow = now();

        try {
            $this->roles();
            $this->fixedAssets($realNow);
            $this->vatAndVds($realNow);
            $this->importPayments($realNow);
            $this->dealerTargets($realNow);
            $this->cheques($realNow);
            $this->schemes($realNow);
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('Demo extras seeded: schemes, targets, cheques, fixed assets, VAT returns and VDS, import payments, roles.');
    }

    private function at(Carbon $when): void
    {
        Carbon::setTestNow($when);
    }

    private function roles(): void
    {
        Role::firstOrCreate(['name' => 'Store Keeper', 'guard_name' => 'web'])
            ->syncPermissions(['inventory.view', 'inventory.manage', 'purchase.view', 'production.view', 'damage.view', 'damage.manage']);
        Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web'])
            ->syncPermissions(['collection.view', 'collection.manage', 'retailers.view', 'sales.view']);
    }

    private function fixedAssets(Carbon $now): void
    {
        $code = fn ($c) => Account::where('code', $c)->value('id');
        $start = $now->copy()->subMonths(11)->startOfMonth();
        $svc = app(FixedAssetService::class);

        $this->at($start->copy()->addDays(2)->setTime(11, 0));
        foreach ([
            ['Factory shed and godown', 'building', '1210', 4500000, 240, 0, 'Factory'],
            ['Fertilizer mixer line', 'machinery', '1220', 1800000, 120, 50000, 'Factory'],
            ['Bagging and sealing machine', 'machinery', '1220', 650000, 96, 20000, 'Factory'],
            ['Delivery truck (Tata 1613)', 'vehicle', '1230', 2400000, 0, 300000, 'Central Depot'],
            ['Office furniture', 'furniture', '1240', 350000, 60, 0, 'Head office'],
        ] as [$name, $cat, $acc, $cost, $life, $salvage, $loc]) {
            $svc->add([
                'name' => $name, 'category' => $cat, 'account_id' => $code($acc), 'location' => $loc,
                'purchase_date' => now()->toDateString(), 'cost' => $cost, 'salvage_value' => $salvage,
                'method' => $life ? 'straight_line' : 'reducing_balance', 'useful_life_months' => $life ?: 60,
                'rate_percent' => $life ? 0 : 20, 'funded_by' => 'bank',
            ]);
        }

        // Computers bought four months ago.
        $this->at($now->copy()->subMonths(4)->startOfMonth()->addDays(9)->setTime(12, 0));
        $svc->add(['name' => 'Office computers (6)', 'category' => 'computer', 'account_id' => $code('1240'), 'location' => 'Head office',
            'purchase_date' => now()->toDateString(), 'cost' => 420000, 'salvage_value' => 0, 'method' => 'straight_line',
            'useful_life_months' => 36, 'rate_percent' => 0, 'funded_by' => 'bank']);

        // Depreciation up to two months ago; last month is left to run.
        $upTo = $now->copy()->subMonthsNoOverflow(2);
        $this->at($upTo->copy()->endOfMonth()->setTime(18, 0));
        $svc->run($upTo->format('Y-m'));
    }

    private function vatAndVds(Carbon $now): void
    {
        if (! VatService::enabled()) {
            return;
        }
        Company::whereNull('bin')->orderBy('id')->limit(3)->get()->each(fn ($c, $i) => $c->update(['bin' => sprintf('0012345%02d-0101', $i + 1)]));

        $returns = app(VatReturnService::class);
        $vds = app(VdsService::class);
        $supplier = Company::orderBy('id')->first();
        $bank = Account::money()->where('is_bank', true)->orderBy('code')->value('id');
        $bigCustomer = Retailer::whereNull('dealer_id')->whereNull('account_dealer_id')->orderByDesc('current_due')->first();

        for ($m = 11; $m >= 2; $m--) {
            $month = $now->copy()->subMonthsNoOverflow($m)->startOfMonth();

            if ($m <= 4 && $supplier) {
                // A supplier payment with VDS kept back.
                $this->at($month->copy()->addDays(19)->setTime(14, 0));
                $vds->paySupplier($supplier, (int) $bank, 85000, 12750, now()->toDateString(), 'Demo VDS payment',
                    'SUP-6.3-'.$month->format('ym'), $month->copy()->addDays(10)->toDateString(), 97750, 'posted');
            }
            if ($m === 3 && $bigCustomer && $bigCustomer->current_due > 5000) {
                // A large customer kept back VAT and gave its 6.6.
                $this->at($month->copy()->addDays(24)->setTime(15, 0));
                $vds->receiveFromCustomer($bigCustomer->fresh(), 1800, now()->toDateString(), 'CUS-6.6-'.$month->format('ym'), null);
            }

            // The return for this month, filed on the 14th of the next.
            $this->at($month->copy()->addMonth()->addDays(13)->setTime(16, 0));
            $s = $returns->summary($month->format('Y-m'));
            if ($s['output_balance'] > 0 || $s['vds_payable'] > 0) {
                try {
                    $returns->settle($month->format('Y-m'), 'bank', now()->toDateString(), 'TR-'.$month->format('ym').'-DEMO');
                } catch (\RuntimeException) {
                    // nothing to settle that month
                }
            }
        }
    }

    private function importPayments(Carbon $now): void
    {
        $bank = Account::money()->where('is_bank', true)->orderBy('code')->value('id');
        foreach (ImportShipment::where('status', 'received')->with('items')->get() as $s) {
            $owed = $s->foreignOutstanding();
            if ($owed <= 0) {
                continue;
            }
            $when = Carbon::parse($s->received_date ?? $now)->addDays(7)->setTime(12, 0);
            $this->at($when->gt($now) ? $now->copy() : $when);
            // The bank's rate on the day was 1.5 taka higher than the LC rate.
            app(ImportService::class)->settle($s, now()->toDateString(), $owed, round($owed * ((float) $s->exchange_rate + 1.5), 2), (int) $bank, 'TT-DEMO-'.$s->id);
        }
    }

    private function dealerTargets(Carbon $now): void
    {
        $svc = app(DealerTargetService::class);
        $dealers = Tenant::bypass(fn () => Dealer::orderBy('id')->get());

        foreach ([3, 2, 1, 0] as $back) {
            $period = $now->copy()->subMonthsNoOverflow($back)->format('Y-m');
            foreach ($dealers as $i => $d) {
                $t = DealerTarget::firstOrCreate(['dealer_id' => $d->id, 'period' => $period], ['target_amount' => 1, 'incentive_percent' => 1.5]);
                $achieved = $svc->achieved($t);
                // Most dealers reach it; one falls short each month.
                $reach = ($i + $back) % 3 !== 0;
                $target = $back === 0
                    ? max(100000, round(($svc->achieved(new DealerTarget(['dealer_id' => $d->id, 'period' => $now->copy()->subMonthNoOverflow()->format('Y-m')])) ?: 300000) * 1.1, -4))
                    : max(50000, round($achieved * ($reach ? 0.9 : 1.15), -4));
                $t->update(['target_amount' => $target]);

                // Older months are already posted; last month waits on the Post button.
                if ($back >= 2 && $reach && $achieved > 0) {
                    $this->at($now->copy()->subMonthsNoOverflow($back)->endOfMonth()->addDays(3)->setTime(11, 0));
                    try {
                        $svc->post($t->fresh());
                    } catch (\RuntimeException) {
                        // not reached after all
                    }
                }
            }
        }
    }

    private function cheques(Carbon $now): void
    {
        $svc = app(ChequeService::class);
        $customers = Retailer::whereNull('dealer_id')->whereNull('account_dealer_id')->where('current_due', '>', 20000)->orderByDesc('current_due')->limit(8)->get();
        if ($customers->count() < 4) {
            return;
        }
        $banks = ['Sonali Bank', 'Islami Bank', 'Dutch-Bangla Bank', 'BRAC Bank', 'Agrani Bank', 'Pubali Bank'];

        // [days ago received, days from received to the cheque date, what happens]
        $plan = [[40, 0, 'cleared'], [33, 5, 'cleared'], [28, 3, 'bounced'], [20, 0, 'cleared'], [9, 4, 'deposited'],
            [6, 2, 'deposited'], [4, 3, 'in_hand'], [2, 15, 'in_hand'], [1, 30, 'in_hand']];
        foreach ($plan as $i => [$ago, $pdc, $fate]) {
            $c = $customers[$i % $customers->count()]->fresh();
            $received = $now->copy()->subDays($ago)->setTime(11, 0);
            $this->at($received);
            $cheque = $svc->receive([
                'retailer_id' => $c->id, 'cheque_no' => (string) (600120 + $i * 37), 'bank_name' => $banks[$i % count($banks)],
                'branch' => ['Gazipur', 'Bogura', 'Jashore', 'Dhaka'][$i % 4], 'received_date' => $received->toDateString(),
                'cheque_date' => $received->copy()->addDays($pdc)->toDateString(),
                'amount' => min(round((float) $c->current_due * 0.4, -3), 150000) ?: 10000,
            ]);
            if ($fate === 'in_hand') {
                continue;
            }
            $this->at($received->copy()->addDays($pdc)->setTime(10, 0));
            $svc->deposit($cheque, now()->toDateString());
            if ($fate === 'deposited') {
                continue;
            }
            $this->at($received->copy()->addDays($pdc + 2)->setTime(15, 0));
            $svc->clear($cheque->fresh(), now()->toDateString());
            if ($fate === 'bounced') {
                $this->at($received->copy()->addDays($pdc + 4)->setTime(12, 0));
                $svc->bounce($cheque->fresh(), now()->toDateString(), 500, 'Insufficient funds');
            }
        }
    }

    private function schemes(Carbon $now): void
    {
        $this->at($now->copy()->setTime(9, 0));
        $npk = Product::where('sku', 'AGT-NPK50')->first();
        $zinc = Product::where('sku', 'AGT-ZN25')->first();
        $feed = Product::where('sku', 'AGT-LF50')->first();

        Scheme::create(['name' => 'Demo: Rabi season — buy 20 NPK, get 1 Zinc free', 'type' => 'free_goods', 'audience' => 'all',
            'product_id' => $npk->id, 'min_qty' => 20, 'free_product_id' => $zinc->id, 'free_qty' => 1,
            'starts_on' => $now->copy()->startOfMonth(), 'ends_on' => $now->copy()->addMonths(2)->endOfMonth(), 'active' => true]);
        Scheme::create(['name' => 'Demo: Feed volume discount 4% (30+ bags)', 'type' => 'discount', 'audience' => 'retailers',
            'category_id' => $feed->category_id, 'min_qty' => 30, 'discount_percent' => 4,
            'starts_on' => $now->copy()->startOfMonth(), 'ends_on' => $now->copy()->endOfMonth(), 'active' => true]);
        Scheme::create(['name' => 'Demo: Monsoon dealer offer 2%', 'type' => 'discount', 'audience' => 'dealers',
            'category_id' => $npk->category_id, 'min_qty' => 100, 'discount_percent' => 2,
            'starts_on' => $now->copy()->subMonths(5)->startOfMonth(), 'ends_on' => $now->copy()->subMonths(3)->endOfMonth(), 'active' => true]);

        // Two pending orders that use the running schemes.
        $wh = Stock::where('product_id', $npk->id)->where('quantity', '>', 60)
            ->whereIn('warehouse_id', fn ($q) => $q->select('id')->from('warehouses')->whereNull('dealer_id')->whereNotIn('purpose', ['damage', 'raw_material']))
            ->value('warehouse_id');
        $customers = Retailer::whereNull('dealer_id')->whereNull('account_dealer_id')->orderBy('id')->limit(2)->get();
        if (! $wh || $customers->count() < 2) {
            return;
        }
        foreach ([[$customers[0], $npk, 40], [$customers[1], $feed, 30]] as [$c, $p, $qty]) {
            $order = SalesOrder::create([
                'number' => app(NumberGeneratorService::class)->generate('sales_order'), 'order_type' => 'retailer',
                'retailer_id' => $c->id, 'warehouse_id' => $wh, 'status' => 'pending', 'order_date' => today(),
                'subtotal' => 0, 'discount' => 0, 'grand_total' => 0,
            ]);
            SalesOrderItem::create(['sales_order_id' => $order->id, 'product_id' => $p->id, 'quantity' => $qty, 'unit_price' => $p->priceFor($c)]);
            app(SchemeService::class)->apply($order->fresh());
        }
    }
}
