<?php

namespace App\Services;

use App\Models\Accounts\Account;
use App\Models\Area;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Retailer;
use App\Models\StockAdjustment;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Accounts\OpeningBalanceService;
use App\Support\Modules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk import from a spreadsheet when going live: products, customers,
 * suppliers and opening stock.
 *
 * Each import is checked first (every row, every error with its line number)
 * and then saved in one transaction, so a file goes in whole or not at all.
 * Products, customers and suppliers are matched on their code: a row whose
 * code exists updates that record. Category, brand, company and area are
 * found by name or code and created when missing.
 *
 * Opening amounts (a customer's due, what we owe a supplier, the value of
 * opening stock) go into the single Opening Balance voucher, so the books
 * stay balanced and the Opening Balances screen shows them.
 */
class DataImportService
{
    /** Columns of each import: key => [label, required, example]. */
    public const TYPES = [
        'products' => [
            'sku' => ['SKU', true, 'FG-001'],
            'name' => ['Name', true, 'Urea Fertilizer 50kg'],
            'type' => ['Type', false, 'finished_good'],
            'category' => ['Category', true, 'Fertilizer'],
            'brand' => ['Brand', false, 'Agro Gold'],
            'company' => ['Company', false, ''],
            'unit' => ['Unit', true, 'bag'],
            'purchase_price' => ['Purchase Price', false, '1200'],
            'sale_price' => ['Retailer Price', false, '1350'],
            'dealer_price' => ['Dealer Price', false, '1300'],
            'mrp' => ['MRP', false, '1400'],
            'vat_rate' => ['VAT %', false, '0'],
            'hs_code' => ['HS Code', false, ''],
            'barcode' => ['Barcode', false, ''],
            'reorder_level' => ['Reorder Level', false, '50'],
            'shelf_life_days' => ['Shelf Life Days', false, '365'],
        ],
        'customers' => [
            'code' => ['Code', true, 'C-0001'],
            'name' => ['Name', true, 'Rahim Uddin'],
            'shop_name' => ['Shop Name', false, 'Rahim Traders'],
            'phone' => ['Phone', true, '01700000000'],
            'address' => ['Address', false, 'Gazipur Sadar'],
            'area' => ['Area', true, 'Gazipur'],
            'bin' => ['BIN', false, ''],
            'credit_limit' => ['Credit Limit', false, '50000'],
            'opening_due' => ['Opening Due', false, '12500'],
        ],
        'suppliers' => [
            'code' => ['Code', true, 'S-001'],
            'name' => ['Name', true, 'Karnaphuli Fertilizer Co.'],
            'phone' => ['Phone', false, '01800000000'],
            'email' => ['Email', false, ''],
            'address' => ['Address', false, 'Chattogram'],
            'country' => ['Country', false, 'Bangladesh'],
            'currency' => ['Currency', false, 'BDT'],
            'opening_payable' => ['Opening Payable', false, '250000'],
        ],
        'opening_stock' => [
            'sku' => ['SKU', true, 'FG-001'],
            'warehouse' => ['Warehouse', true, 'Main Warehouse'],
            'quantity' => ['Quantity', true, '100'],
            'unit_cost' => ['Unit Cost', false, '1200'],
            'batch_no' => ['Batch No', false, 'OPEN-001'],
            'expiry_date' => ['Expiry Date', false, '2027-12-31'],
        ],
    ];

    public const LABELS = [
        'products' => 'Products',
        'customers' => 'Customers',
        'suppliers' => 'Suppliers',
        'opening_stock' => 'Opening stock',
    ];

    public function __construct(
        private InventoryService $inventory,
        private OpeningBalanceService $opening,
    ) {}

    /** Header and one example row, for the downloadable template. */
    public function template(string $type): array
    {
        $cols = self::TYPES[$type];

        return [
            array_map(fn ($c) => $c[0].($c[1] ? ' *' : ''), array_values($cols)),
            array_map(fn ($c) => $c[2], array_values($cols)),
        ];
    }

    /**
     * Check every row.
     *
     * @param  array<int, array<string, string>>  $rows  line => row (from SpreadsheetReader)
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, array<int, string>>, missing: array<int, string>}
     */
    public function check(string $type, array $rows): array
    {
        $cols = self::TYPES[$type];
        $aliases = $this->aliases($type);

        // Accept the label ("Retailer Price") as well as the key ("sale_price").
        $rows = array_map(function ($row) use ($aliases) {
            $out = [];
            foreach ($row as $k => $v) {
                $out[$aliases[$k] ?? $k] = $v;
            }

            return $out;
        }, $rows);

        $present = $rows ? array_keys(reset($rows)) : [];
        $missing = [];
        foreach ($cols as $key => [$label, $required]) {
            if ($required && ! in_array($key, $present, true)) {
                $missing[] = $label;
            }
        }
        if ($missing || ! $rows) {
            return ['rows' => [], 'errors' => [], 'missing' => $missing ?: [__('The file has no rows.')]];
        }

        $errors = [];
        $seen = [];
        $out = [];
        foreach ($rows as $line => $row) {
            $e = [];
            foreach ($cols as $key => [$label, $required]) {
                if ($required && ($row[$key] ?? '') === '') {
                    $e[] = __(':field is required.', ['field' => __($label)]);
                }
            }
            $e = array_merge($e, $this->{'check'.Str::studly($type)}($row));

            $dupKey = $type === 'opening_stock' ? null : ($type === 'products' ? 'sku' : 'code');
            if ($dupKey && ($row[$dupKey] ?? '') !== '') {
                $k = strtolower($row[$dupKey]);
                if (isset($seen[$k])) {
                    $e[] = __(':value is also on line :line.', ['value' => $row[$dupKey], 'line' => $seen[$k]]);
                }
                $seen[$k] = $line;
            }

            if ($e) {
                $errors[$line] = $e;
            }
            $out[$line] = $row;
        }

        return ['rows' => $out, 'errors' => $errors, 'missing' => []];
    }

    /**
     * Save checked rows. Run check() first; this throws if anything is wrong.
     *
     * @return array{created: int, updated: int}
     */
    public function import(string $type, array $rows, ?string $date = null): array
    {
        $checked = $this->check($type, $rows);
        if ($checked['missing'] || $checked['errors']) {
            throw new \RuntimeException(__('The file has errors. Fix them and upload again.'));
        }

        return DB::transaction(fn () => $this->{'import'.Str::studly($type)}($checked['rows'], $date));
    }

    /** @return array<string, string> lower-cased label key => column key */
    private function aliases(string $type): array
    {
        $map = [];
        foreach (self::TYPES[$type] as $key => [$label]) {
            $map[\App\Support\SpreadsheetReader::key($label)] = $key;
        }
        $map['retailer_price'] = 'sale_price';
        $map['vat'] = 'vat_rate';

        return $map;
    }

    // ---------------------------------------------------------------- checks

    private function number(array $row, string $key, string $label, bool $positive = false): array
    {
        $v = $row[$key] ?? '';
        if ($v === '') {
            return [];
        }
        $v = str_replace(',', '', $v);
        if (! is_numeric($v) || (float) $v < 0 || ($positive && (float) $v <= 0)) {
            return [__(':field must be a number:more.', ['field' => __($label), 'more' => $positive ? ' '.__('above zero') : ''])];
        }

        return [];
    }

    private function checkProducts(array $row): array
    {
        $e = [];
        foreach (['purchase_price' => 'Purchase Price', 'sale_price' => 'Retailer Price', 'dealer_price' => 'Dealer Price', 'mrp' => 'MRP', 'vat_rate' => 'VAT %', 'reorder_level' => 'Reorder Level', 'shelf_life_days' => 'Shelf Life Days'] as $k => $l) {
            $e = array_merge($e, $this->number($row, $k, $l));
        }
        if (($row['type'] ?? '') !== '' && ! $this->productType($row['type'])) {
            $e[] = __('Type must be one of: :list.', ['list' => implode(', ', array_keys(Product::TYPES))]);
        }
        if (($row['unit'] ?? '') !== '' && ! $this->unit($row['unit'])) {
            $e[] = __('Unit ":unit" was not found. Add it under Units first.', ['unit' => $row['unit']]);
        }
        if (($row['company'] ?? '') === '' && ! Company::exists()) {
            $e[] = __('Company is required: there is no company yet.');
        }

        return $e;
    }

    private function checkCustomers(array $row): array
    {
        $e = array_merge($this->number($row, 'credit_limit', 'Credit Limit'), $this->numberSigned($row, 'opening_due', 'Opening Due'));
        $existing = ($row['code'] ?? '') !== '' ? \App\Support\Tenant::bypass(fn () => Retailer::where('code', $row['code'])->first()) : null;
        if ($existing && $existing->dealer_id !== \App\Support\Tenant::dealerId()) {
            $e[] = __('Code :code belongs to another business.', ['code' => $row['code']]);
        }

        return $e;
    }

    private function checkSuppliers(array $row): array
    {
        $e = $this->numberSigned($row, 'opening_payable', 'Opening Payable');
        if (($row['email'] ?? '') !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $e[] = __('Email is not valid.');
        }

        return $e;
    }

    private function checkOpeningStock(array $row): array
    {
        $e = array_merge($this->number($row, 'quantity', 'Quantity', true), $this->number($row, 'unit_cost', 'Unit Cost'));
        if (($row['sku'] ?? '') !== '' && ! Product::where('sku', $row['sku'])->exists()) {
            $e[] = __('No product with SKU :sku. Import products first.', ['sku' => $row['sku']]);
        }
        if (($row['warehouse'] ?? '') !== '' && ! $this->warehouse($row['warehouse'])) {
            $e[] = __('Warehouse ":name" was not found.', ['name' => $row['warehouse']]);
        }
        if (($row['expiry_date'] ?? '') !== '' && ! $this->date($row['expiry_date'])) {
            $e[] = __('Expiry Date must be a date like 2027-12-31.');
        }

        return $e;
    }

    private function numberSigned(array $row, string $key, string $label): array
    {
        $v = str_replace(',', '', $row[$key] ?? '');

        return $v === '' || is_numeric($v) ? [] : [__(':field must be a number.', ['field' => __($label)])];
    }

    // --------------------------------------------------------------- imports

    private function importProducts(array $rows): array
    {
        $created = $updated = 0;
        $defaultType = Modules::type() === 'trading' ? 'trading' : 'finished_good';
        $defaultCompany = Company::orderBy('id')->first();

        foreach ($rows as $row) {
            $data = [
                'sku' => $row['sku'],
                'name' => $row['name'],
                'type' => $this->productType($row['type'] ?? '') ?? $defaultType,
                'category_id' => $this->findOrMake(Category::class, $row['category'])->id,
                'unit_id' => $this->unit($row['unit'])->id,
                'company_id' => ($row['company'] ?? '') !== '' ? $this->findOrMake(Company::class, $row['company'])->id : $defaultCompany->id,
            ];
            if (($row['brand'] ?? '') !== '') {
                $data['brand_id'] = $this->findOrMake(Brand::class, $row['brand'], ['company_id' => $data['company_id']])->id;
            }
            foreach (['purchase_price', 'sale_price', 'dealer_price', 'mrp', 'vat_rate', 'reorder_level'] as $k) {
                if (($row[$k] ?? '') !== '') {
                    $data[$k] = (float) str_replace(',', '', $row[$k]);
                }
            }
            foreach (['hs_code', 'barcode'] as $k) {
                if (($row[$k] ?? '') !== '') {
                    $data[$k] = $row[$k];
                }
            }
            if (($row['shelf_life_days'] ?? '') !== '') {
                $data['shelf_life_days'] = (int) $row['shelf_life_days'];
            }

            $product = Product::where('sku', $row['sku'])->first();
            $before = $product ? [(float) $product->purchase_price, (float) $product->sale_price, (float) $product->dealer_price, (float) $product->mrp] : null;
            if ($product) {
                $product->update($data);
                $updated++;
            } else {
                $product = Product::create($data + ['active' => true]);
                $created++;
            }

            $after = [(float) $product->purchase_price, (float) $product->sale_price, (float) $product->dealer_price, (float) $product->mrp];
            if ($before !== $after) {
                ProductPrice::create([
                    'product_id' => $product->id,
                    'purchase_price' => $after[0],
                    'sale_price' => $after[1],
                    'dealer_price' => $after[2],
                    'mrp' => $after[3],
                    'effective_from' => today(),
                    'created_by' => Auth::id(),
                ]);
            }
        }

        return compact('created', 'updated');
    }

    private function importCustomers(array $rows, ?string $date): array
    {
        $created = $updated = 0;
        $opening = [];
        foreach ($rows as $row) {
            $data = [
                'name' => $row['name'],
                'phone' => $row['phone'],
                'area_id' => $this->findOrMake(Area::class, $row['area'])->id,
            ];
            foreach (['shop_name', 'address', 'bin'] as $k) {
                if (($row[$k] ?? '') !== '') {
                    $data[$k] = $row[$k];
                }
            }
            if (($row['credit_limit'] ?? '') !== '') {
                $data['credit_limit'] = (float) str_replace(',', '', $row['credit_limit']);
            }

            $retailer = Retailer::where('code', $row['code'])->first();
            if ($retailer) {
                $retailer->update($data);
                $updated++;
            } else {
                $retailer = Retailer::create($data + ['code' => $row['code'], 'active' => true]);
                $created++;
            }
            if (($row['opening_due'] ?? '') !== '') {
                $opening[$retailer->id] = (float) str_replace(',', '', $row['opening_due']);
            }
        }

        if ($opening) {
            $this->mergeOpening($date, customers: $opening);
        }

        return compact('created', 'updated');
    }

    private function importSuppliers(array $rows, ?string $date): array
    {
        $created = $updated = 0;
        $opening = [];
        foreach ($rows as $row) {
            $data = ['name' => $row['name']];
            foreach (['phone', 'email', 'address', 'country', 'currency'] as $k) {
                if (($row[$k] ?? '') !== '') {
                    $data[$k] = $k === 'currency' ? strtoupper($row[$k]) : $row[$k];
                }
            }

            $company = Company::where('code', $row['code'])->first();
            if ($company) {
                $company->update($data);
                $updated++;
            } else {
                $company = Company::create($data + ['code' => $row['code'], 'active' => true]);
                $created++;
            }
            if (($row['opening_payable'] ?? '') !== '') {
                $opening[$company->id] = (float) str_replace(',', '', $row['opening_payable']);
            }
        }

        if ($opening) {
            $this->mergeOpening($date, suppliers: $opening);
        }

        return compact('created', 'updated');
    }

    /** Opening stock is added on top of what is there: importing twice adds twice. */
    private function importOpeningStock(array $rows, ?string $date): array
    {
        $date ??= $this->opening->current()['date'];
        $value = 0.0;
        $created = 0;
        foreach ($rows as $line => $row) {
            $product = Product::where('sku', $row['sku'])->first();
            $warehouse = $this->warehouse($row['warehouse']);
            $qty = round((float) str_replace(',', '', $row['quantity']), 3);
            $cost = ($row['unit_cost'] ?? '') !== '' ? (float) str_replace(',', '', $row['unit_cost']) : (float) $product->purchase_price;

            $ref = StockAdjustment::create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity_change' => $qty,
                'reason' => __('Opening stock'),
                'adjusted_by' => Auth::id(),
                'adjustment_date' => $date,
            ]);
            $batch = $this->inventory->receiveStock(
                $product->id,
                $warehouse->id,
                ($row['batch_no'] ?? '') !== '' ? $row['batch_no'] : 'OPEN-'.str_replace('-', '', $date).'-'.$line,
                ($row['expiry_date'] ?? '') !== '' ? $this->date($row['expiry_date']) : null,
                $cost,
                $qty,
                $ref,
            );
            $ref->update(['batch_id' => $batch->id]);
            $value += $qty * $cost;
            $created++;
        }

        $inventory = Account::where('system_key', 'inventory')->value('id');
        $this->mergeOpening($date, amounts: [$inventory => round($value, 2)], add: true);

        return ['created' => $created, 'updated' => 0];
    }

    /**
     * Put amounts into the opening voucher. Customer and supplier amounts
     * replace that party's opening; account amounts are added when $add.
     */
    private function mergeOpening(?string $date, array $amounts = [], array $customers = [], array $suppliers = [], bool $add = false): void
    {
        $current = $this->opening->current();
        foreach ($amounts as $id => $v) {
            $current['amounts'][$id] = round(($add ? ($current['amounts'][$id] ?? 0) : 0) + $v, 2);
        }
        $current['customers'] = array_replace($current['customers'], $customers);
        $current['suppliers'] = array_replace($current['suppliers'], $suppliers);

        $this->opening->save($date ?: $current['date'], $current['amounts'], $current['customers'], $current['suppliers']);
    }

    // ---------------------------------------------------------------- lookups

    private function productType(string $value): ?string
    {
        $v = Str::snake(strtolower(trim($value)));
        if (isset(Product::TYPES[$v])) {
            return $v;
        }
        foreach (Product::TYPES as $key => $label) {
            if (strcasecmp($label, trim($value)) === 0) {
                return $key;
            }
        }

        return null;
    }

    private function unit(string $value): ?Unit
    {
        return Unit::where('short_code', $value)->orWhere('name', $value)->first();
    }

    private function warehouse(string $value): ?Warehouse
    {
        return Warehouse::where('name', $value)->first() ?? (ctype_digit($value) ? Warehouse::find((int) $value) : null);
    }

    private function date(string $value): ?string
    {
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            // Excel date serial number.
            return now()->setDate(1899, 12, 30)->addDays((int) $value)->toDateString();
        }
        try {
            return \Carbon\Carbon::parse(str_replace('/', '-', $value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Find by name or code, or create with a code made from the name. */
    private function findOrMake(string $model, string $name, array $extra = [])
    {
        $found = $model::where('name', $name)->orWhere('code', $name)->first();
        if ($found) {
            return $found;
        }

        $base = strtoupper(Str::limit(Str::slug($name, ''), 8, '')) ?: 'X';
        $code = $base;
        for ($i = 2; $model::where('code', $code)->exists(); $i++) {
            $code = $base.$i;
        }

        return $model::create(['name' => $name, 'code' => $code] + $extra);
    }
}
