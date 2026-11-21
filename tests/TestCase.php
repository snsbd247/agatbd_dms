<?php

namespace Tests;

use App\Models\Area;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the app for a test with two safety nets:
     *  - storage (backups, uploads, logs) goes to a temp folder, not the live one;
     *  - the test stops before anything touches the database unless it is the
     *    in-memory SQLite database, so RefreshDatabase can never wipe real data.
     */
    public function createApplication(): Application
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        // One folder per database driver, so the SQLite and MySQL suites can run side by side.
        $storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dms-test-storage'.(getenv('DB_CONNECTION') === 'mysql' ? '-mysql' : '');
        foreach (['app/public', 'app/private', 'framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'backups'] as $dir) {
            $path = $storage.DIRECTORY_SEPARATOR.$dir;
            is_dir($path) || mkdir($path, 0777, true);   // facades are not available yet
        }
        $app->useStoragePath($storage);

        $app->make(Kernel::class)->bootstrap();

        $default = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$default}.database");

        // Allowed: in-memory SQLite (phpunit.xml), or a MySQL/MariaDB database
        // whose name ends in "_test" (phpunit.mysql.xml). Never the live "dms".
        $memory = $default === 'sqlite' && $database === ':memory:';
        $mysqlTest = in_array($default, ['mysql', 'mariadb'], true) && is_string($database) && str_ends_with($database, '_test');

        if (! $memory && ! $mysqlTest) {
            fwrite(STDERR, "\nREFUSING TO RUN TESTS: database is [{$default}] {$database}, expected sqlite :memory: or a *_test MySQL database.\n"
                ."Run `php artisan config:clear` and check phpunit.xml.\n");
            exit(1);
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        \App\Support\Tenant::reset();   // static state must not leak between tests
    }

    protected function seedRoles(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    protected function warehouse(): Warehouse
    {
        return Warehouse::firstOrCreate(['name' => 'Central'], ['type' => 'central', 'active' => true]);
    }

    protected function product(array $attributes = []): Product
    {
        static $n = 0;
        $n++;

        $company = Company::firstOrCreate(['code' => 'CMP'], ['name' => 'Test Company']);
        $category = Category::firstOrCreate(['code' => 'CAT'], ['name' => 'General']);
        $unit = Unit::firstOrCreate(['short_code' => 'pc'], ['name' => 'Piece']);

        return Product::create(array_merge([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-'.$n.'-'.uniqid(),
            'name' => 'Product '.$n,
            'purchase_price' => 70,
            'sale_price' => 100,
            'active' => true,
        ], $attributes));
    }

    protected function retailer(array $attributes = []): Retailer
    {
        $area = Area::firstOrCreate(['code' => 'AR1'], ['name' => 'Area 1']);

        return Retailer::create(array_merge([
            'area_id' => $area->id,
            'code' => 'R-'.uniqid(),
            'name' => 'Shop '.uniqid(),
            'phone' => '01711000000',
            'credit_limit' => 100000,
            'current_due' => 0,
            'active' => true,
        ], $attributes));
    }

    /**
     * Put stock in through the real InventoryService (batch + stock + ledger).
     */
    protected function receive(Product $product, float $quantity, string $batchNo = 'B1', float $cost = 70): void
    {
        app(InventoryService::class)->receiveStock(
            productId: $product->id,
            warehouseId: $this->warehouse()->id,
            batchNo: $batchNo,
            expiryDate: null,
            costPrice: $cost,
            quantity: $quantity,
            reference: $product,
        );
    }
}
