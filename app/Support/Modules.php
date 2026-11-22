<?php

namespace App\Support;

use App\Models\Settings\AppSetting;

/**
 * Which parts of the system this business uses.
 *
 * The business type (trading, manufacturing or both) decides which modules
 * are available at all; within those, an admin may switch modules off in
 * Settings → Business Type & Modules. Core modules are always on.
 *
 * A switched-off module keeps its data. Its permissions are refused for
 * everyone, Admin included (see AppServiceProvider), so its menus, pages
 * and buttons disappear; pages without a permission of their own use the
 * `module:<key>` route middleware.
 *
 * Nothing chosen yet (an existing install) means every module is on.
 */
class Modules
{
    public const TYPE = 'business_type';
    public const OFF = 'modules_off';

    public const TYPES = [
        'trading' => 'Trading',
        'manufacturing' => 'Manufacturing',
        'both' => 'Trading & Manufacturing',
    ];

    /**
     * key => [label, description, group, core?, requires[], available in types[], on in template for types[], permission prefixes[], coming in phase]
     */
    public static function all(): array
    {
        static $cache = null;

        return $cache ??= self::build();
    }

    private static function build(): array
    {
        $all = ['trading', 'manufacturing', 'both'];
        $make = ['manufacturing', 'both'];
        $trade = ['trading', 'both'];

        return [
            // core
            'master_data' => self::m('Master Data', 'Companies, brands, categories, units and products.', 'core', core: true),
            'inventory' => self::m('Warehouses & Stock', 'Warehouses, batches, stock, transfers and adjustments.', 'core', core: true),
            'purchase' => self::m('Local Purchase', 'Purchase orders, goods receipts and purchase returns.', 'core', core: true),
            'sales' => self::m('Sales, Delivery & Collection', 'Sales orders, delivery, invoices and money collection.', 'core', core: true),
            'retailers' => self::m('Customers (Retailers)', 'Customer list, credit limit, due and ledger.', 'core', core: true),
            'accounts' => self::m('Accounts', 'Double-entry vouchers, ledgers and financial statements.', 'core', core: true),
            'reports' => self::m('Reports', 'Sales, stock, collection, profit and other reports.', 'core', core: true),

            // selling
            'dealers' => self::m('Dealers & Dealer Panel', 'Dealers with their own login, stock, sales and books; ordering from the head office.', 'selling', on: $all, perms: ['dealers.']),
            'representatives' => self::m('Marketing Staff', 'Sales representatives, targets, commission and their old payroll screens.', 'selling', on: $all, perms: ['representatives.', 'payroll.']),
            'retailer_portal' => self::m('Retailer Portal', 'Retailers log in to order, see their statement, returns and warranty.', 'selling', on: []),
            'returns' => self::m('Returns', 'Customer returns and returns to suppliers.', 'selling', on: $all, perms: ['returns.']),
            'warranty' => self::m('Warranty', 'Warranty policies and claims.', 'selling', on: [], perms: ['warranty.']),

            // making
            'production' => self::m('Factory & Production', 'Formulas, production batches with QC and costing, material planning, machines, maintenance and batch trace.', 'making', available: $make, on: $make, perms: ['production.']),
            'damage' => self::m('Damage & Repair', 'Damage register, repair, discount sale and write-off.', 'making', on: $all, perms: ['damage.']),

            // people
            'hr' => self::m('HR & Payroll', 'Employees, attendance, leave, loans and the salary sheet.', 'people', on: $all, perms: ['hr.']),
            'attendance_devices' => self::m('Attendance Machines', 'ZKTeco machines send punches straight to the system.', 'people', requires: ['hr'], on: $make),

            // tax
            'vat' => self::m('VAT (Mushak)', 'VAT inside sale prices, Mushak 6.3 invoices and the monthly VAT report.', 'tax', on: $all),

            // coming
            // pricing & buying abroad
            'costing' => self::m('Costing & Price Declaration', 'Cost sheet (purchase or formula + overheads + margin), dealer / retailer / MRP prices, Mushak 4.3.', 'pricing', on: $all, perms: ['costing.']),
            'import' => self::m('Import (LC & Landed Cost)', 'Proforma, LC, shipment, customs duties, C&F and landed cost into stock.', 'pricing', on: $all, perms: ['import.']),
            'kits' => self::m('Kits & Repacking', 'Make a pack or kit from several products, or repack bulk goods into small packs.', 'making', available: $trade, on: $trade, perms: ['kits.']),
        ];
    }

    public const GROUPS = [
        'core' => 'Always on',
        'selling' => 'Selling & distribution',
        'making' => 'Factory & stock care',
        'people' => 'People',
        'tax' => 'Tax',
        'pricing' => 'Pricing & import',
        'coming' => 'Coming next',
    ];

    private static function m(string $label, string $description, string $group, bool $core = false, array $requires = [], ?array $available = null, array $on = [], array $perms = [], ?string $coming = null): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'group' => $group,
            'core' => $core,
            'requires' => $requires,
            'available' => $available ?? ['trading', 'manufacturing', 'both'],
            'on' => $core ? ['trading', 'manufacturing', 'both'] : $on,
            'perms' => $perms,
            'coming' => $coming,
        ];
    }

    public static function type(): ?string
    {
        $type = AppSetting::get(self::TYPE);

        return isset(self::TYPES[$type]) ? $type : null;
    }

    /** @return array<int, string> */
    public static function off(): array
    {
        return array_values(array_filter((array) json_decode((string) AppSetting::get(self::OFF, '[]'), true), 'is_string'));
    }

    public static function available(string $key): bool
    {
        $m = self::all()[$key] ?? null;
        $type = self::type();

        return $m !== null && $m['coming'] === null && ($type === null || in_array($type, $m['available'], true));
    }

    public static function enabled(string $key): bool
    {
        $m = self::all()[$key] ?? null;
        if (! $m || ! self::available($key)) {
            return false;
        }
        if ($m['core']) {
            return true;
        }
        if (in_array($key, self::off(), true)) {
            return false;
        }
        foreach ($m['requires'] as $r) {
            if (! self::enabled($r)) {
                return false;
            }
        }

        return true;
    }

    /** The module a permission belongs to, if it is not core. */
    public static function forAbility(string $ability): ?string
    {
        foreach (self::all() as $key => $m) {
            foreach ($m['perms'] as $prefix) {
                if (str_starts_with($ability, $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** Should this permission be refused because its module is off? */
    public static function denies($user, string $ability): bool
    {
        // Without the dealers module a dealer login has nothing it may do.
        if ($user->dealer_id && ! self::enabled('dealers')) {
            return true;
        }
        if ($ability === 'portal.access') {
            // Dealers order from the head office through the portal; retailers use it as their portal.
            return $user->dealer_id ? ! self::enabled('dealers') : ! self::enabled('retailer_portal');
        }
        $module = self::forAbility($ability);

        return $module !== null && ! self::enabled($module);
    }

    /** Switch to a business type and load its template: its modules on, the rest off. */
    public static function applyTemplate(string $type): void
    {
        abort_unless(isset(self::TYPES[$type]), 422);
        $off = [];
        foreach (self::all() as $key => $m) {
            if (! $m['core'] && $m['coming'] === null && ! in_array($type, $m['on'], true)) {
                $off[] = $key;
            }
        }
        AppSetting::put([self::TYPE => $type, self::OFF => json_encode($off)]);
    }

    /**
     * Switch one module. Turning one off also turns off what needs it;
     * turning one on also turns on what it needs.
     *
     * @return array<int, string> the other modules that changed with it
     */
    public static function set(string $key, bool $on): array
    {
        $m = self::all()[$key] ?? null;
        abort_if(! $m || $m['core'] || ! self::available($key), 422);

        $off = self::off();
        $changed = [];

        if ($on) {
            $todo = [$key];
            while ($todo) {
                $k = array_shift($todo);
                if (in_array($k, $off, true)) {
                    $off = array_values(array_diff($off, [$k]));
                    $k !== $key && $changed[] = $k;
                }
                foreach (self::all()[$k]['requires'] as $r) {
                    $todo[] = $r;
                }
            }
        } else {
            $todo = [$key];
            while ($todo) {
                $k = array_shift($todo);
                if (! in_array($k, $off, true)) {
                    $off[] = $k;
                    $k !== $key && $changed[] = $k;
                }
                foreach (self::all() as $other => $om) {
                    if (in_array($k, $om['requires'], true) && ! in_array($other, $off, true)) {
                        $todo[] = $other;
                    }
                }
            }
        }

        AppSetting::put([self::OFF => json_encode(array_values($off))]);

        return $changed;
    }

    /** Modules that need this one (to warn before switching it off). */
    public static function dependents(string $key): array
    {
        return array_keys(array_filter(self::all(), fn ($m) => in_array($key, $m['requires'], true)));
    }
}
