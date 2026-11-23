<?php

namespace App\Services;

use App\Models\Settings\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears demo or test data before going live.
 *
 *  - "transactions": every order, invoice, voucher, stock movement, payroll
 *    and attendance record goes; products, customers, suppliers, dealers,
 *    warehouses, employees and the like stay, with stock and dues at zero.
 *  - "all": master data goes too. What stays: head-office user accounts,
 *    roles, settings, company profile, chart of accounts, units and the
 *    small lookup lists (departments, leave types, cost heads ...).
 *
 * A backup is taken first by the caller. Every table must be listed in one
 * of the lists below; a test fails when a new table is not, so nothing is
 * left behind or wiped by accident.
 */
class DataResetService
{
    /** Never touched. */
    public const KEEP = [
        'migrations', 'users', 'roles', 'permissions', 'model_has_permissions', 'model_has_roles', 'role_has_permissions',
        'app_settings', 'company_profiles', 'accounts', 'units', 'unit_conversions', 'cost_heads', 'leave_types',
        'warranty_policies', 'departments', 'designations', 'shifts', 'holidays', 'login_histories',
        'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    /** Cleared by both modes. */
    public const TRANSACTIONS = [
        'advances', 'assembly_order_items', 'assembly_orders', 'attendance_punches', 'attendances', 'audit_logs',
        'batches', 'bonuses', 'cheques', 'collections', 'commissions', 'company_return_items', 'company_returns',
        'cost_sheet_items', 'cost_sheets', 'damage_actions', 'damage_records', 'dealer_targets', 'delivery_invoice_items',
        'delivery_invoices', 'delivery_note_items', 'delivery_notes', 'employee_loans', 'final_settlements',
        'fiscal_year_closings', 'goods_receipt_items', 'goods_receipts', 'import_costs', 'import_items',
        'import_shipments', 'journal_lines', 'journal_entries', 'leave_applications', 'machine_maintenances',
        'notifications', 'numbering_sequences', 'payroll_entries', 'payroll_runs', 'payslips', 'production_issues',
        'production_orders', 'production_qc_checks', 'purchase_order_items', 'purchase_orders',
        'purchase_return_items', 'purchase_returns', 'repair_jobs', 'representative_targets', 'retailer_ledger',
        'return_receive_items', 'return_receives', 'return_request_items', 'return_requests', 'sales_invoice_items',
        'sales_invoices', 'sales_order_items', 'sales_orders', 'sms_logs', 'stock_adjustments', 'stock_ledger',
        'stock_transfer_items', 'stock_transfers', 'stocks', 'vat_returns', 'warranty_claims', 'warranty_tracking',
    ];

    /** Cleared only by "all". */
    public const MASTER = [
        'areas', 'brands', 'categories', 'companies', 'products', 'product_prices', 'product_units', 'boms',
        'bom_items', 'dealers', 'dealer_representative', 'employees', 'factories', 'machines', 'representatives',
        'retailers', 'schemes', 'warehouses', 'attendance_devices',
    ];

    /** Tables in the database that none of the lists name. */
    public function unclassified(): array
    {
        $known = array_merge(self::KEEP, self::TRANSACTIONS, self::MASTER);

        return array_values(array_diff($this->tables(), $known));
    }

    /**
     * @return array<string, int> table => rows removed
     */
    public function reset(string $mode, ?User $keep = null): array
    {
        if (! in_array($mode, ['transactions', 'all'], true)) {
            throw new \InvalidArgumentException("Unknown reset mode $mode");
        }
        if ($missing = $this->unclassified()) {
            throw new \RuntimeException('Unclassified tables: '.implode(', ', $missing));
        }

        $existing = $this->tables();
        $wipe = array_values(array_intersect($mode === 'all' ? array_merge(self::TRANSACTIONS, self::MASTER) : self::TRANSACTIONS, $existing));
        $removed = [];

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($mode, $wipe, $keep, &$removed) {
                if ($mode === 'all') {
                    $removed['users'] = $this->removeBusinessUsers($keep);
                }
                // Foreign-key checks cannot always be switched off inside a
                // transaction (SQLite), so delete in passes: a table whose rows
                // are still referenced waits until its children are gone.
                $pending = $wipe;
                while ($pending) {
                    $left = [];
                    $lastError = null;
                    foreach ($pending as $table) {
                        try {
                            $removed[$table] = DB::table($table)->delete();
                        } catch (\Illuminate\Database\QueryException $e) {
                            $left[] = $table;
                            $lastError = $e;
                        }
                    }
                    if (count($left) === count($pending)) {
                        throw $lastError;
                    }
                    $pending = $left;
                }
                if ($mode === 'transactions') {
                    DB::table('retailers')->update(['current_due' => 0]);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if ($mode === 'all') {
            AppSetting::put(['setup_done' => '0']);
        }
        AppSetting::flush();

        return array_filter($removed);
    }

    /**
     * Logins that belong to dealers, customers, representatives or employees.
     * Head-office staff logins (and $keep) stay.
     */
    private function removeBusinessUsers(?User $keep): int
    {
        $ids = DB::table('users')->whereNotNull('dealer_id')->pluck('id')
            ->merge(DB::table('retailers')->whereNotNull('user_id')->pluck('user_id'))
            ->merge(DB::table('representatives')->whereNotNull('user_id')->pluck('user_id'))
            ->merge(Schema::hasColumn('employees', 'user_id') ? DB::table('employees')->whereNotNull('user_id')->pluck('user_id') : collect())
            ->unique()
            ->reject(fn ($id) => $keep && (int) $id === $keep->id)
            ->values();

        DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $ids)->delete();
        DB::table('model_has_permissions')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $ids)->delete();

        return DB::table('users')->whereIn('id', $ids)->delete();
    }

    /** @return array<int, string> */
    private function tables(): array
    {
        $prefix = DB::connection()->getTablePrefix();

        // On MySQL, getTables() can list other databases the user can see:
        // keep only this connection's own database.
        $db = DB::connection()->getDatabaseName();
        $driver = DB::connection()->getDriverName();

        return collect(Schema::getTables())
            ->filter(fn ($t) => ! in_array($driver, ['mysql', 'mariadb'], true) || ($t['schema'] ?? $db) === $db)
            ->pluck('name')
            ->map(fn ($t) => $prefix && str_starts_with($t, $prefix) ? substr($t, strlen($prefix)) : $t)
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->values()->all();
    }
}
