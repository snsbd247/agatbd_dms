<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public const ROLE_PERMISSIONS = [
        'Dealer' => PermissionSeeder::PERMISSIONS,
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
            'reports.view',
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
