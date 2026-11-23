<?php

namespace App\Support;

use App\Models\User;

/**
 * The one menu of the app, grouped by module under section headings. The
 * desktop sidebar and the phone "More" page both draw from here, so they
 * always show the same pages in the same order.
 *
 * A link shows when the user has its permission (the link's own or its
 * group's), its module is on (switched-off modules also deny their
 * permissions, see Modules), and it is not head-office-only for a dealer
 * login. A group shows when at least one of its links does; a section
 * shows when at least one of its groups does.
 */
class Navigation
{
    /** @return array<int, array{label: string, groups: array}> */
    public static function definition(?User $user = null): array
    {
        $dealer = (bool) $user?->dealer_id;

        return [
            ['label' => '', 'groups' => [
                ['icon' => 'building-storefront', 'label' => $dealer ? 'Buy from Agro Tech' : 'My Account', 'role' => ['Retailer', 'Dealer'], 'permission' => 'portal.access', 'links' => [
                    ['route' => 'portal.orders', 'label' => 'My Orders', 'icon' => 'shopping-bag'],
                    ['route' => 'portal.orders.create', 'label' => 'New Order', 'icon' => 'shopping-cart'],
                    ['route' => 'portal.statement', 'label' => 'My Statement', 'icon' => 'book-open'],
                    ['route' => 'portal.returns', 'label' => 'My Returns', 'icon' => 'refresh'],
                    ['route' => 'portal.warranty', 'label' => 'My Warranty Claims', 'icon' => 'shield-check'],
                ]],
            ]],

            ['label' => 'Sales & Customers', 'groups' => [
                ['icon' => 'shopping-bag', 'label' => 'Sales', 'links' => [
                    ['route' => 'sales.orders.index', 'label' => 'Sales Orders', 'icon' => 'shopping-bag', 'permission' => 'sales.view'],
                    ['route' => 'delivery.index', 'label' => 'Deliveries', 'icon' => 'truck', 'permission' => 'delivery.view'],
                    ['route' => 'delivery.invoices.index', 'label' => 'Delivery Invoices', 'icon' => 'document-text', 'permission' => 'delivery.view'],
                    ['route' => 'collection.index', 'label' => 'Collections', 'icon' => 'banknotes', 'permission' => 'collection.view'],
                ]],
                ['icon' => 'building-storefront', 'label' => 'Customers', 'links' => [
                    ['route' => 'retailers.index', 'label' => 'Retailers', 'icon' => 'building-storefront', 'permission' => 'retailers.view'],
                    ['route' => 'dealers.index', 'label' => 'Dealers', 'icon' => 'building-storefront', 'permission' => 'dealers.view'],
                    ['route' => 'inventory.areas', 'label' => 'Areas', 'icon' => 'map-pin', 'permission' => 'inventory.view', 'headOffice' => true],
                ]],
                ['icon' => 'user-group', 'label' => 'Marketing Staff', 'links' => [
                    ['route' => 'representatives.index', 'label' => 'Representatives', 'icon' => 'user-group', 'permission' => 'representatives.view'],
                    ['route' => 'payroll.index', 'label' => 'Payroll Runs', 'icon' => 'wallet', 'permission' => 'payroll.view'],
                    ['route' => 'payroll.advances.index', 'label' => 'Advances', 'icon' => 'credit-card', 'permission' => 'payroll.view'],
                    ['route' => 'payroll.bonuses.index', 'label' => 'Bonuses', 'icon' => 'gift', 'permission' => 'payroll.view'],
                ]],
                ['icon' => 'shield-check', 'label' => 'Return & Warranty', 'links' => [
                    ['route' => 'returns.index', 'label' => 'Return Requests', 'icon' => 'refresh', 'permission' => 'returns.view'],
                    ['route' => 'warranty.index', 'label' => 'Warranty Claims', 'icon' => 'shield-exclamation', 'permission' => 'warranty.view'],
                ]],
            ]],

            ['label' => 'Purchase & Stock', 'groups' => [
                ['icon' => 'shopping-cart', 'label' => 'Purchase', 'links' => [
                    ['route' => 'purchase.orders.index', 'label' => 'Purchase Orders', 'icon' => 'shopping-cart', 'permission' => 'purchase.view'],
                    ['route' => 'purchase.receipts.index', 'label' => 'Goods Receipts', 'icon' => 'download', 'permission' => 'purchase.view'],
                    ['route' => 'purchase.returns.index', 'label' => 'Purchase Returns', 'icon' => 'refresh', 'permission' => 'purchase.view'],
                    ['route' => 'returns.company.index', 'label' => 'Company Returns', 'icon' => 'truck', 'permission' => 'returns.view'],
                    ['route' => 'accounts.supplier-payments', 'label' => 'Supplier Payments', 'icon' => 'credit-card', 'permission' => 'accounts.view', 'headOffice' => true],
                    ['route' => 'import.index', 'label' => 'Import Shipments', 'icon' => 'globe', 'permission' => 'import.view', 'headOffice' => true],
                ]],
                ['icon' => 'archive-box', 'label' => 'Inventory', 'links' => [
                    ['route' => 'reports.stock', 'label' => 'Stock Overview', 'icon' => 'chart-bar', 'permission' => 'reports.view'],
                    ['route' => 'inventory.warehouses', 'label' => 'Warehouses', 'icon' => 'archive-box', 'permission' => 'inventory.view'],
                    ['route' => 'inventory.factories', 'label' => 'Factories', 'icon' => 'factory', 'permission' => 'inventory.view', 'headOffice' => true, 'module' => 'production'],
                    ['route' => 'inventory.transfers', 'label' => 'Stock Transfers', 'icon' => 'truck', 'permission' => 'inventory.view'],
                    ['route' => 'inventory.adjustments', 'label' => 'Stock Adjustments', 'icon' => 'refresh', 'permission' => 'inventory.view'],
                    ['route' => 'kits.index', 'label' => 'Kits & Repacking', 'icon' => 'gift', 'permission' => 'kits.view', 'headOffice' => true],
                ]],
                ['icon' => 'exclamation-triangle', 'label' => 'Damage & Repair', 'links' => [
                    ['route' => 'damage.index', 'label' => 'Damage Register', 'icon' => 'exclamation-triangle', 'permission' => 'damage.view'],
                    ['route' => 'damage.repairs', 'label' => 'Repair Jobs', 'icon' => 'cog', 'permission' => 'damage.view'],
                ]],
            ]],

            ['label' => 'Manufacturing', 'groups' => [
                ['icon' => 'factory', 'label' => 'Production', 'permission' => 'production.view', 'headOffice' => true, 'links' => [
                    ['route' => 'production.orders.index', 'label' => 'Production Batches', 'icon' => 'factory'],
                    ['route' => 'production.formulas', 'label' => 'Formulas', 'icon' => 'document-text'],
                    ['route' => 'production.planning', 'label' => 'Material Planning', 'icon' => 'calendar'],
                    ['route' => 'production.trace', 'label' => 'Batch Trace', 'icon' => 'search'],
                    ['route' => 'production.report', 'label' => 'Production Report', 'icon' => 'chart-bar'],
                ]],
                ['icon' => 'cog', 'label' => 'Machines', 'permission' => 'production.view', 'headOffice' => true, 'links' => [
                    ['route' => 'production.machines', 'label' => 'Machines & Lines', 'icon' => 'cog'],
                    ['route' => 'production.maintenance', 'label' => 'Machine Maintenance', 'icon' => 'cog'],
                ]],
                ['icon' => 'trending-up', 'label' => 'Costing & Prices', 'permission' => 'costing.view', 'headOffice' => true, 'links' => [
                    ['route' => 'costing.index', 'label' => 'Cost Sheets', 'icon' => 'document-text'],
                    ['route' => 'costing.heads', 'label' => 'Cost Heads', 'icon' => 'cog'],
                ]],
            ]],

            ['label' => 'People', 'groups' => [
                ['icon' => 'user-group', 'label' => 'HR & Payroll', 'permission' => 'hr.view', 'headOffice' => true, 'links' => [
                    ['route' => 'hr.employees.index', 'label' => 'Employees', 'icon' => 'user-group'],
                    ['route' => 'hr.attendance', 'label' => 'Attendance', 'icon' => 'clock'],
                    ['route' => 'hr.devices', 'label' => 'Attendance Machines', 'icon' => 'database', 'module' => 'attendance_devices'],
                    ['route' => 'hr.leaves', 'label' => 'Leave', 'icon' => 'calendar'],
                    ['route' => 'hr.loans', 'label' => 'Advances & Loans', 'icon' => 'credit-card'],
                    ['route' => 'hr.payroll.index', 'label' => 'Salary Sheets', 'icon' => 'wallet'],
                    ['route' => 'hr.setup', 'label' => 'HR Setup', 'icon' => 'cog'],
                ]],
            ]],

            ['label' => 'Finance', 'groups' => [
                ['icon' => 'book-open', 'label' => 'Accounts', 'permission' => 'accounts.view', 'links' => [
                    ['route' => 'accounts.vouchers.index', 'label' => 'Vouchers', 'icon' => 'document-text'],
                    ['route' => 'accounts.expenses', 'label' => 'Expenses', 'icon' => 'banknotes'],
                    ['route' => 'accounts.bank', 'label' => 'Bank Reconciliation', 'icon' => 'shield-check'],
                    ['route' => 'accounts.chart', 'label' => 'Chart of Accounts', 'icon' => 'database'],
                    ['route' => 'accounts.opening', 'label' => 'Opening Balances', 'icon' => 'calendar'],
                    ['route' => 'accounts.year-closing', 'label' => 'Year Closing', 'icon' => 'lock-closed'],
                ]],
                ['icon' => 'chart-bar', 'label' => 'Financial Statements', 'permission' => 'accounts.view', 'links' => [
                    ['route' => 'accounts.ledger', 'label' => 'General Ledger', 'icon' => 'book-open'],
                    ['route' => 'accounts.reports', 'params' => ['report' => 'trial-balance'], 'label' => 'Trial Balance', 'icon' => 'chart-bar'],
                    ['route' => 'accounts.reports', 'params' => ['report' => 'profit-loss'], 'label' => 'Profit & Loss Account', 'icon' => 'trending-up'],
                    ['route' => 'accounts.reports', 'params' => ['report' => 'balance-sheet'], 'label' => 'Balance Sheet', 'icon' => 'archive-box'],
                    ['route' => 'accounts.reports', 'params' => ['report' => 'cash-flow'], 'label' => 'Cash Flow Statement', 'icon' => 'wallet'],
                    ['route' => 'accounts.reports', 'params' => ['report' => 'receivables'], 'label' => 'Receivables & Payables', 'icon' => 'clock'],
                ]],
                ['icon' => 'document-text', 'label' => 'VAT (Mushak)', 'permission' => 'reports.view', 'module' => 'vat', 'links' => [
                    ['route' => 'reports.vat', 'label' => 'VAT Report', 'icon' => 'document-text'],
                ]],
            ]],

            ['label' => 'Reports', 'groups' => [
                ['icon' => 'chart-bar', 'label' => 'Sales Reports', 'permission' => 'reports.view', 'links' => [
                    ['route' => 'reports.sales', 'label' => 'Sales Report', 'icon' => 'chart-bar'],
                    ['route' => 'reports.collections', 'label' => 'Collections & Due', 'icon' => 'banknotes'],
                    ['route' => 'reports.products', 'label' => 'Product Performance', 'icon' => 'trending-up'],
                    ['route' => 'reports.retailers', 'label' => 'Retailer Report', 'icon' => 'building-storefront'],
                    ['route' => 'reports.dealers', 'label' => 'Dealer Sales Report', 'icon' => 'building-storefront', 'permission' => 'dealers.view'],
                    ['route' => 'reports.areas', 'label' => 'Area Report', 'icon' => 'map-pin'],
                    ['route' => 'reports.representatives', 'label' => 'Representative Performance', 'icon' => 'user-group', 'module' => 'representatives'],
                    ['route' => 'reports.returns', 'label' => 'Return Report', 'icon' => 'refresh', 'module' => 'returns'],
                    ['route' => 'reports.warranty', 'label' => 'Warranty Report', 'icon' => 'shield-exclamation', 'module' => 'warranty'],
                ]],
                ['icon' => 'archive-box', 'label' => 'Purchase & Stock Reports', 'permission' => 'reports.view', 'links' => [
                    ['route' => 'reports.purchase', 'label' => 'Purchase Report', 'icon' => 'shopping-cart', 'headOffice' => true],
                    ['route' => 'reports.companies', 'label' => 'Company Report', 'icon' => 'gift', 'headOffice' => true],
                    ['route' => 'reports.expiry', 'label' => 'Expiry Report', 'icon' => 'clock'],
                ]],
                ['icon' => 'trending-up', 'label' => 'Profit & Payroll Reports', 'permission' => 'reports.view', 'links' => [
                    ['route' => 'reports.profit', 'label' => 'Profit & Loss', 'icon' => 'credit-card'],
                    ['route' => 'reports.payroll', 'label' => 'Payroll & Commission', 'icon' => 'wallet', 'headOffice' => true, 'module' => 'representatives'],
                ]],
            ]],

            ['label' => 'Setup', 'groups' => [
                ['icon' => 'database', 'label' => 'Master Data', 'permission' => 'master-data.view', 'links' => [
                    ['route' => 'master-data.products', 'label' => 'Products', 'icon' => 'archive-box'],
                    ['route' => 'master-data.categories', 'label' => 'Categories', 'icon' => 'grid'],
                    ['route' => 'master-data.brands', 'label' => 'Brands', 'icon' => 'gift'],
                    ['route' => 'master-data.companies', 'label' => 'Companies', 'icon' => 'building-storefront'],
                    ['route' => 'master-data.units', 'label' => 'Units', 'icon' => 'database'],
                ]],
                ['icon' => 'cog', 'label' => 'Settings', 'permission' => 'settings.manage', 'links' => [
                    ['route' => 'settings.company-profile', 'label' => 'Company Profile', 'icon' => 'building-storefront'],
                    ['route' => 'settings.modules', 'label' => 'Business Type & Modules', 'icon' => 'grid'],
                    ['route' => 'settings.setup', 'label' => 'Setup Wizard', 'icon' => 'cog', 'headOffice' => true],
                    ['route' => 'settings.users', 'label' => 'Users', 'icon' => 'user'],
                    ['route' => 'settings.numbering', 'label' => 'Numbering', 'icon' => 'database'],
                    ['route' => 'settings.messaging', 'label' => 'Email & SMS', 'icon' => 'mail'],
                    ['route' => 'settings.warranty-policies', 'label' => 'Warranty Policies', 'icon' => 'shield-check', 'module' => 'warranty'],
                ]],
                ['icon' => 'lock-closed', 'label' => 'Data & Security', 'permission' => 'settings.manage', 'links' => [
                    ['route' => 'settings.data-import', 'label' => 'Data Import', 'icon' => 'download'],
                    ['route' => 'more.backup-restore', 'label' => 'Backup & Restore', 'icon' => 'archive-box'],
                    ['route' => 'settings.login-history', 'label' => 'Login History', 'icon' => 'clock'],
                    ['route' => 'settings.audit-log', 'label' => 'Audit Log', 'icon' => 'book-open'],
                ]],
            ]],
        ];
    }

    /**
     * The menu this user may see: hidden links, empty groups and empty
     * sections removed; each link gets 'href' and 'active', each group 'active'.
     */
    public static function for(User $user): array
    {
        $active = self::activeLinkKey($user);
        $out = [];

        foreach (self::definition($user) as $section) {
            $groups = [];
            foreach ($section['groups'] as $group) {
                if (isset($group['role']) && ! $user->hasRole($group['role'])) {
                    continue;
                }
                $links = [];
                foreach ($group['links'] as $link) {
                    if (! self::visible($user, $group, $link)) {
                        continue;
                    }
                    $link['href'] = route($link['route'], $link['params'] ?? []);
                    $link['active'] = self::key($link) === $active;
                    $links[] = $link;
                }
                if ($links) {
                    $group['links'] = $links;
                    $group['active'] = collect($links)->contains('active', true);
                    $groups[] = $group;
                }
            }
            if ($groups) {
                $out[] = ['label' => $section['label'], 'groups' => $groups];
            }
        }

        return $out;
    }

    /** Label of the group holding the current page, for the breadcrumb. */
    public static function activeGroupLabel(array $menu): ?string
    {
        foreach ($menu as $section) {
            foreach ($section['groups'] as $group) {
                if ($group['active']) {
                    return $group['label'];
                }
            }
        }

        return null;
    }

    private static function visible(User $user, array $group, array $link): bool
    {
        $perm = $link['permission'] ?? $group['permission'] ?? null;
        if ($perm && ! collect((array) $perm)->contains(fn ($p) => $user->can($p))) {
            return false;
        }
        if ((! empty($link['headOffice']) || ! empty($group['headOffice'])) && $user->dealer_id) {
            return false;
        }
        foreach ([$group['module'] ?? null, $link['module'] ?? null] as $module) {
            if ($module && ! Modules::enabled($module)) {
                return false;
            }
        }

        return true;
    }

    private static function key(array $link): string
    {
        return $link['route'].(isset($link['params']) ? '?'.http_build_query($link['params']) : '');
    }

    /**
     * Which link the current page belongs to. Each link also covers the
     * pages under it ("sales.orders.index" covers "sales.orders.show"); when
     * several match, the most specific one wins ("delivery.invoices.index"
     * over "delivery.index").
     */
    private static function activeLinkKey(User $user): ?string
    {
        $request = request();
        $route = $request->route()?->getName();
        if (! $route) {
            return null;
        }

        $best = null;
        $bestLen = -1;
        foreach (self::definition($user) as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['links'] as $link) {
                    if (isset($link['params'])) {
                        if ($route === $link['route'] && $request->route('report') === $link['params']['report']) {
                            return self::key($link);
                        }

                        continue;
                    }
                    $base = preg_replace('/\.index$/', '', $link['route']);
                    if ($route === $link['route'] || $route === $base || str_starts_with($route, $base.'.')) {
                        if (strlen($base) > $bestLen) {
                            $best = self::key($link);
                            $bestLen = strlen($base);
                        }
                    }
                }
            }
        }

        return $best;
    }
}
