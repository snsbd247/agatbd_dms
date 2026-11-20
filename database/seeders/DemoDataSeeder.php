<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Representative;
use App\Models\Retailer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Demo users get DEMO_PASSWORD from .env, or a random password when it is
     * not set. Never hard-code a shared password here: this app is reachable
     * from the internet.
     */
    private function demoPassword(): string
    {
        return env('DEMO_PASSWORD') ?: \Illuminate\Support\Str::password(16);
    }

    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@dms.local'],
            ['name' => 'DMS Dealer', 'password' => $this->demoPassword()]
        );
        $admin->assignRole('Dealer');

        $manager = User::firstOrCreate(
            ['email' => 'manager@dms.local'],
            ['name' => 'DMS Manager', 'password' => $this->demoPassword()]
        );
        $manager->assignRole('Manager');

        $companies = collect([
            ['name' => 'Unilever Bangladesh', 'code' => 'UNBD'],
            ['name' => 'Square Toiletries', 'code' => 'SQTL'],
            ['name' => 'ACI Limited', 'code' => 'ACI'],
        ])->mapWithKeys(fn ($c) => [$c['code'] => Company::firstOrCreate(['code' => $c['code']], $c)]);

        $brands = collect([
            ['company' => 'UNBD', 'name' => 'Lux', 'code' => 'LUX'],
            ['company' => 'UNBD', 'name' => 'Sunsilk', 'code' => 'SUNSILK'],
            ['company' => 'SQTL', 'name' => 'Meril', 'code' => 'MERIL'],
            ['company' => 'ACI', 'name' => 'Savlon', 'code' => 'SAVLON'],
        ])->mapWithKeys(function ($b) use ($companies) {
            return [$b['code'] => Brand::firstOrCreate(
                ['code' => $b['code']],
                ['name' => $b['name'], 'company_id' => $companies[$b['company']]->id]
            )];
        });

        $categories = collect([
            ['name' => 'Personal Care', 'code' => 'PCARE'],
            ['name' => 'Home Care', 'code' => 'HCARE'],
            ['name' => 'Food & Beverage', 'code' => 'FOOD'],
        ])->mapWithKeys(fn ($c) => [$c['code'] => Category::firstOrCreate(['code' => $c['code']], $c)]);

        $units = collect([
            ['name' => 'Piece', 'short_code' => 'PCS'],
            ['name' => 'Box', 'short_code' => 'BOX'],
            ['name' => 'Carton', 'short_code' => 'CTN'],
        ])->mapWithKeys(fn ($u) => [$u['short_code'] => Unit::firstOrCreate(['short_code' => $u['short_code']], $u)]);

        $products = [
            ['sku' => 'LUX-SOAP-100', 'name' => 'Lux Soap 100g', 'company' => 'UNBD', 'brand' => 'LUX', 'category' => 'PCARE', 'unit' => 'PCS', 'purchase_price' => 25, 'sale_price' => 32, 'reorder_level' => 50],
            ['sku' => 'SUN-SHMP-200', 'name' => 'Sunsilk Shampoo 200ml', 'company' => 'UNBD', 'brand' => 'SUNSILK', 'category' => 'PCARE', 'unit' => 'PCS', 'purchase_price' => 140, 'sale_price' => 175, 'reorder_level' => 30],
            ['sku' => 'MERIL-TALC-100', 'name' => 'Meril Talcum Powder 100g', 'company' => 'SQTL', 'brand' => 'MERIL', 'category' => 'PCARE', 'unit' => 'PCS', 'purchase_price' => 60, 'sale_price' => 78, 'reorder_level' => 40],
            ['sku' => 'SAVLON-HW-250', 'name' => 'Savlon Handwash 250ml', 'company' => 'ACI', 'brand' => 'SAVLON', 'category' => 'HCARE', 'unit' => 'PCS', 'purchase_price' => 90, 'sale_price' => 115, 'reorder_level' => 25],
            ['sku' => 'SAVLON-AS-100', 'name' => 'Savlon Antiseptic 100ml', 'company' => 'ACI', 'brand' => 'SAVLON', 'category' => 'HCARE', 'unit' => 'PCS', 'purchase_price' => 45, 'sale_price' => 58, 'reorder_level' => 40],
            ['sku' => 'LUX-BODYWASH-200', 'name' => 'Lux Body Wash 200ml', 'company' => 'UNBD', 'brand' => 'LUX', 'category' => 'PCARE', 'unit' => 'PCS', 'purchase_price' => 150, 'sale_price' => 190, 'reorder_level' => 20],
            ['sku' => 'MERIL-BOX-50', 'name' => 'Meril Talcum Powder Box (50pcs)', 'company' => 'SQTL', 'brand' => 'MERIL', 'category' => 'PCARE', 'unit' => 'BOX', 'purchase_price' => 2800, 'sale_price' => 3500, 'reorder_level' => 5],
            ['sku' => 'SUNSILK-CTN-24', 'name' => 'Sunsilk Shampoo Carton (24pcs)', 'company' => 'UNBD', 'brand' => 'SUNSILK', 'category' => 'PCARE', 'unit' => 'CTN', 'purchase_price' => 3200, 'sale_price' => 4000, 'reorder_level' => 5],
        ];

        foreach ($products as $data) {
            $product = Product::firstOrCreate(
                ['sku' => $data['sku']],
                [
                    'name' => $data['name'],
                    'company_id' => $companies[$data['company']]->id,
                    'brand_id' => $brands[$data['brand']]->id,
                    'category_id' => $categories[$data['category']]->id,
                    'unit_id' => $units[$data['unit']]->id,
                    'purchase_price' => $data['purchase_price'],
                    'sale_price' => $data['sale_price'],
                    'reorder_level' => $data['reorder_level'],
                    'active' => true,
                ]
            );

            ProductPrice::firstOrCreate(
                ['product_id' => $product->id, 'effective_from' => today()],
                [
                    'purchase_price' => $data['purchase_price'],
                    'sale_price' => $data['sale_price'],
                    'created_by' => $admin->id,
                ]
            );
        }

        Warehouse::firstOrCreate(['name' => 'Central Warehouse'], ['type' => 'central']);
        Warehouse::firstOrCreate(['name' => 'Dhaka Branch'], ['type' => 'branch']);

        $areaNorth = Area::firstOrCreate(['code' => 'DHK-N'], ['name' => 'Dhaka North']);
        Area::firstOrCreate(['code' => 'DHK-S'], ['name' => 'Dhaka South']);

        $repUser = User::firstOrCreate(
            ['email' => 'rep1@dms.local'],
            ['name' => 'Rahim Representative', 'password' => $this->demoPassword()]
        );
        $repUser->assignRole('Sales Representative');

        $representative = Representative::firstOrCreate(
            ['employee_code' => 'REP-001'],
            [
                'user_id' => $repUser->id,
                'name' => 'Rahim Representative',
                'phone' => '01700000001',
                'area_id' => $areaNorth->id,
                'salary_base' => 15000,
                'commission_rate' => 2.5,
                'target_amount' => 200000,
                'active' => true,
            ]
        );

        $retailerUser = User::firstOrCreate(
            ['email' => 'retailer1@dms.local'],
            ['name' => 'Karim Store', 'password' => $this->demoPassword()]
        );
        $retailerUser->assignRole('Retailer');

        Retailer::firstOrCreate(
            ['code' => 'RTL-001'],
            [
                'user_id' => $retailerUser->id,
                'representative_id' => $representative->id,
                'area_id' => $areaNorth->id,
                'name' => 'Karim Store',
                'shop_name' => 'Karim General Store',
                'phone' => '01800000001',
                'address' => 'Mirpur, Dhaka',
                'credit_limit' => 50000,
                'current_due' => 0,
                'active' => true,
            ]
        );
    }
}
