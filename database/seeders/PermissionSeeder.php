<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
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
        'payroll.view', 'payroll.manage',
        'reports.view',
        'dealers.view', 'dealers.manage',
        'settings.manage',
        'portal.access',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
