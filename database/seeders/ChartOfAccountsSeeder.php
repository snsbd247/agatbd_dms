<?php

namespace Database\Seeders;

use App\Models\Accounts\Account;
use Illuminate\Database\Seeder;

/**
 * The default chart of accounts. Accounts with a system_key are used by the
 * automatic postings (sales, purchases, collections, payroll …) and cannot be
 * deleted. Safe to run again: existing codes are left as they are.
 *
 * Format: [code, name, type, parent code, system key, flags]
 */
class ChartOfAccountsSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['1000', 'Assets', 'asset', null, null, ['group']],
        ['1100', 'Current Assets', 'asset', '1000', null, ['group']],
        ['1110', 'Cash in Hand', 'asset', '1100', 'cash', ['cash']],
        ['1120', 'Bank Accounts', 'asset', '1100', null, ['group']],
        ['1121', 'Main Bank Account', 'asset', '1120', 'bank', ['bank']],
        ['1130', 'Mobile Banking (bKash/Nagad)', 'asset', '1100', 'mobile', ['cash']],
        ['1140', 'Accounts Receivable (Customers)', 'asset', '1100', 'ar', []],
        ['1150', 'Inventory', 'asset', '1100', 'inventory', []],
        ['1155', 'Work in Process', 'asset', '1100', 'wip', []],
        ['1160', 'Employee Advances', 'asset', '1100', 'employee_advance', []],
        ['1170', 'Input VAT (Receivable)', 'asset', '1100', 'vat_input', []],
        ['1175', 'Advance Income Tax (AIT)', 'asset', '1100', 'advance_tax', []],
        ['1180', 'Advance Paid to Suppliers', 'asset', '1100', null, []],
        ['1185', 'Goods in Transit (Import)', 'asset', '1100', 'goods_in_transit', []],
        ['1186', 'LC Margin', 'asset', '1100', 'lc_margin', []],
        ['1200', 'Fixed Assets', 'asset', '1000', null, ['group']],
        ['1210', 'Land & Building', 'asset', '1200', null, []],
        ['1220', 'Plant & Machinery', 'asset', '1200', null, []],
        ['1230', 'Vehicles', 'asset', '1200', null, []],
        ['1240', 'Furniture & Office Equipment', 'asset', '1200', null, []],
        ['1290', 'Accumulated Depreciation', 'asset', '1200', 'accum_depreciation', []],

        ['2000', 'Liabilities', 'liability', null, null, ['group']],
        ['2100', 'Current Liabilities', 'liability', '2000', null, ['group']],
        ['2110', 'Accounts Payable (Suppliers)', 'liability', '2100', 'ap', []],
        ['2120', 'Salaries Payable', 'liability', '2100', null, []],
        ['2130', 'Output VAT (Payable)', 'liability', '2100', 'vat_output', []],
        ['2135', 'VDS Payable (VAT deducted at source)', 'liability', '2100', 'vds_payable', []],
        ['2140', 'Tax Payable (AIT/TDS)', 'liability', '2100', null, []],
        ['2150', 'Advance from Customers', 'liability', '2100', null, []],
        ['2200', 'Long-term Liabilities', 'liability', '2000', null, ['group']],
        ['2210', 'Bank Loan', 'liability', '2200', null, []],

        ['3000', 'Equity', 'equity', null, null, ['group']],
        ['3100', 'Owner\'s Capital', 'equity', '3000', 'capital', []],
        ['3200', 'Retained Earnings', 'equity', '3000', 'retained_earnings', []],
        ['3300', 'Opening Balance Equity', 'equity', '3000', 'opening_equity', []],
        ['3400', 'Drawings', 'equity', '3000', null, []],

        ['4000', 'Income', 'income', null, null, ['group']],
        ['4100', 'Sales', 'income', '4000', 'sales', []],
        ['4110', 'Sales Discount', 'income', '4000', 'sales_discount', []],
        ['4120', 'Sales Returns', 'income', '4000', 'sales_return', []],
        ['4200', 'Other Income', 'income', '4000', 'other_income', []],
        ['4210', 'Stock Gain', 'income', '4000', 'stock_gain', []],
        ['4220', 'Foreign Exchange Gain/Loss', 'income', '4000', 'fx_gain_loss', []],

        ['5000', 'Expenses', 'expense', null, null, ['group']],
        ['5100', 'Cost of Goods Sold', 'expense', '5000', 'cogs', []],
        ['5110', 'Production Labour & Overhead Applied', 'expense', '5000', 'production_applied', []],
        ['5200', 'Operating Expenses', 'expense', '5000', null, ['group']],
        ['5210', 'Salaries & Wages', 'expense', '5200', 'salary', []],
        ['5220', 'Sales Commission', 'expense', '5200', 'commission', []],
        ['5230', 'Bonus', 'expense', '5200', 'bonus', []],
        ['5235', 'Gratuity & Leave Encashment', 'expense', '5200', 'gratuity', []],
        ['5236', 'Overtime', 'expense', '5200', 'overtime', []],
        ['5240', 'Rent', 'expense', '5200', null, []],
        ['5250', 'Utilities (Electricity, Water, Gas)', 'expense', '5200', null, []],
        ['5260', 'Transport & Delivery', 'expense', '5200', null, []],
        ['5270', 'Office Expenses', 'expense', '5200', null, []],
        ['5280', 'Marketing & Promotion', 'expense', '5200', null, []],
        ['5285', 'Dealer Incentives', 'expense', '5200', 'dealer_incentive', []],
        ['5290', 'Repairs & Maintenance', 'expense', '5200', 'repairs', []],
        ['5300', 'Bank Charges', 'expense', '5200', null, []],
        ['5310', 'Depreciation', 'expense', '5200', 'depreciation', []],
        ['5320', 'Stock Loss & Damage', 'expense', '5200', 'stock_loss', []],
        ['5390', 'Miscellaneous Expenses', 'expense', '5200', null, []],
    ];

    public function run(): void
    {
        $ids = [];

        foreach (self::ACCOUNTS as [$code, $name, $type, $parent, $key, $flags]) {
            $account = Account::withoutGlobalScopes()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parent ? ($ids[$parent] ?? Account::withoutGlobalScopes()->where('code', $parent)->value('id')) : null,
                'is_group' => in_array('group', $flags, true),
                'system_key' => $key,
                'is_cash' => in_array('cash', $flags, true),
                'is_bank' => in_array('bank', $flags, true),
                'active' => true,
            ]);

            $ids[$code] = $account->id;
        }
    }
}
