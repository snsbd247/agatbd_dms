<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Admin = Agro Tech head office, can do everything (Gate::before).
     * Dealer = a dealer's own login: its panel, limited to its own records
     * by App\Support\Tenant, plus ordering from Agro Tech through the portal.
     */
    public const ROLE_PERMISSIONS = [
        'Admin' => PermissionSeeder::PERMISSIONS,
        'Manager' => [
            'master-data.view', 'master-data.manage',
            'purchase.view', 'purchase.manage',
            'inventory.view', 'inventory.manage',
            'retailers.view', 'retailers.manage',
            'representatives.view', 'representatives.manage',
            'sales.view', 'sales.create', 'sales.approve',
            'delivery.view', 'delivery.manage',
            'collection.view', 'collection.manage',
            'returns.view', 'returns.create', 'returns.manage',
            'warranty.view', 'warranty.manage',
            'dealers.view', 'dealers.manage',
            'accounts.view',
            'hr.view',
            'production.view',
            'reports.view',
        ],
        'Factory Manager' => [
            'master-data.view',
            'inventory.view', 'inventory.manage',
            'production.view', 'production.manage', 'production.qc',
            'reports.view',
        ],
        'HR Manager' => [
            'hr.view', 'hr.manage', 'hr.payroll',
            'reports.view',
        ],
        'Accountant' => [
            'master-data.view',
            'purchase.view', 'inventory.view', 'sales.view', 'collection.view', 'returns.view',
            'accounts.view', 'accounts.manage', 'accounts.approve',
            'hr.view', 'hr.payroll',
            'production.view',
            'reports.view',
        ],
        'Dealer' => [
            'inventory.view', 'inventory.manage',
            'retailers.view', 'retailers.manage',
            'representatives.view', 'representatives.manage',
            'sales.view', 'sales.create', 'sales.approve',
            'delivery.view', 'delivery.manage',
            'collection.view', 'collection.manage',
            'returns.view', 'returns.create', 'returns.manage',
            'warranty.view', 'warranty.manage',
            'reports.view',
            'accounts.view', 'accounts.manage', 'accounts.approve',
            'portal.access',
        ],
        'Sales Representative' => [
            'retailers.view',
            'sales.view', 'sales.create',
            'delivery.view', 'delivery.manage',
            'collection.view', 'collection.manage',
            'returns.view', 'returns.create',
        ],
        'Retailer' => ['portal.access'],
    ];

    public function run(): void
    {
        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            Role::firstOrCreate(['name' => $role])->syncPermissions($permissions);
        }
    }
}
