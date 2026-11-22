<?php

namespace Tests\Feature;

use App\Livewire\Settings\DataImport;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Models\Accounts\JournalLine;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Services\DataImportService;
use App\Services\DataResetService;
use App\Support\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** Going live: bulk import from spreadsheets and clearing demo data. */
class GoLiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->admin = $this->userWithRole('Admin');
        $this->actingAs($this->admin);
        Unit::firstOrCreate(['short_code' => 'bag'], ['name' => 'Bag']);
    }

    private function csv(array $lines): UploadedFile
    {
        $text = "\xEF\xBB\xBF".implode("\n", array_map(fn ($l) => implode(',', $l), $lines));

        return UploadedFile::fake()->createWithContent('import.csv', $text);
    }

    private function balance(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    public function test_the_template_downloads_with_the_columns(): void
    {
        $res = $this->get(route('settings.data-import.template', 'products'))->assertOk();
        $this->assertStringContainsString('"SKU *","Name *"', $res->streamedContent());
        $this->get(route('settings.data-import.template', 'nonsense'))->assertNotFound();
    }

    public function test_products_import_creates_lookups_and_updates_by_sku(): void
    {
        $file = $this->csv([
            ['SKU *', 'Name *', 'Category *', 'Brand', 'Company', 'Unit *', 'Retailer Price', 'Dealer Price', 'MRP', 'Type'],
            ['FG-1', 'Urea 50kg', 'Fertilizer', 'Gold', 'Karnaphuli', 'bag', '1350', '1300', '1400', 'finished_good'],
            ['TR-1', 'Sprayer', 'Tools', '', 'Karnaphuli', 'Bag', '900', '', '', 'Trading good'],
        ]);

        Livewire::test(DataImport::class)
            ->set('file', $file)
            ->call('check')
            ->assertSet('errorsByLine', [])
            ->call('import')
            ->assertSet('result', ['created' => 2, 'updated' => 0]);

        $p = Product::where('sku', 'FG-1')->first();
        $this->assertSame('Fertilizer', $p->category->name);
        $this->assertSame('Gold', $p->brand->name);
        $this->assertSame(1300.0, (float) $p->dealer_price);
        $this->assertSame('trading', Product::where('sku', 'TR-1')->value('type'));
        $this->assertSame(1, Company::where('name', 'Karnaphuli')->count());
        $this->assertSame(1, $p->prices()->count());

        // Same SKU again updates, and records the price change.
        $again = $this->csv([['sku', 'name', 'category', 'unit', 'sale_price'], ['FG-1', 'Urea 50 kg', 'Fertilizer', 'bag', '1380']]);
        Livewire::test(DataImport::class)->set('file', $again)->call('check')->call('import')
            ->assertSet('result', ['created' => 0, 'updated' => 1]);
        $this->assertSame(1380.0, (float) $p->fresh()->sale_price);
        $this->assertSame(2, $p->prices()->count());
    }

    public function test_errors_are_listed_by_line_and_nothing_is_saved(): void
    {
        Company::create(['name' => 'Own', 'code' => 'OWN']);
        $file = $this->csv([
            ['SKU', 'Name', 'Category', 'Unit', 'Retailer Price'],
            ['A-1', 'Good', 'Cat', 'bag', '10'],
            ['A-2', '', 'Cat', 'kg-unknown', 'abc'],
            ['A-1', 'Dup', 'Cat', 'bag', '5'],
        ]);

        $c = Livewire::test(DataImport::class)->set('file', $file)->call('check');
        $errors = $c->get('errorsByLine');
        $this->assertSame([3, 4], array_keys($errors));
        $this->assertCount(3, $errors[3]);   // name, unit, price
        $c->call('import');
        $this->assertSame(0, Product::count());

        $missing = $this->csv([['Name', 'Unit'], ['X', 'bag']]);
        Livewire::test(DataImport::class)->set('file', $missing)->call('check')
            ->assertSet('missing', ['SKU', 'Category']);
    }

    public function test_customers_and_suppliers_bring_their_opening_balances(): void
    {
        $customers = $this->csv([
            ['Code', 'Name', 'Phone', 'Area', 'Opening Due'],
            ['C-1', 'Rahim', '017', 'Gazipur', '"12,500"'],
            ['C-2', 'Karim', '018', 'Gazipur', ''],
        ]);
        Livewire::test(DataImport::class)->set('type', 'customers')->set('file', $customers)->call('check')->call('import')
            ->assertSet('result', ['created' => 2, 'updated' => 0]);

        $rahim = Retailer::where('code', 'C-1')->first();
        $this->assertSame(12500.0, (float) $rahim->current_due);
        $this->assertSame(12500.0, $this->balance('ar'));

        $suppliers = $this->csv([['Code', 'Name', 'Opening Payable'], ['S-1', 'Karnaphuli', '250000']]);
        Livewire::test(DataImport::class)->set('type', 'suppliers')->set('file', $suppliers)->call('check')->call('import');
        $this->assertSame(-250000.0, $this->balance('ap'));

        // Still one opening voucher, balanced through opening equity; re-importing does not double the due.
        Livewire::test(DataImport::class)->set('type', 'customers')->set('file', $this->csv([['Code', 'Name', 'Phone', 'Area', 'Opening Due'], ['C-1', 'Rahim', '017', 'Gazipur', '10000']]))->call('check')->call('import');
        $this->assertSame(1, JournalEntry::where('type', 'opening')->count());
        $this->assertSame(10000.0, (float) $rahim->fresh()->current_due);
        $this->assertSame(10000.0, $this->balance('ar'));
        $this->assertSame(240000.0, $this->balance('opening_equity'));
    }

    public function test_opening_stock_adds_batches_and_inventory_value(): void
    {
        $p = $this->product(['sku' => 'FG-9', 'purchase_price' => 50]);
        $w = $this->warehouse();

        $file = $this->csv([
            ['SKU', 'Warehouse', 'Quantity', 'Unit Cost', 'Batch No', 'Expiry Date'],
            ['FG-9', $w->name, '100', '60', 'OB-1', '2027-12-31'],
            ['FG-9', $w->name, '10', '', '', ''],
            ['NOPE', 'Nowhere', '0', '', '', '31/31/2027'],
        ]);
        $c = Livewire::test(DataImport::class)->set('type', 'opening_stock')->set('file', $file)->call('check');
        $this->assertCount(4, $c->get('errorsByLine')[4]);

        $file = $this->csv([
            ['SKU', 'Warehouse', 'Quantity', 'Unit Cost', 'Batch No', 'Expiry Date'],
            ['FG-9', $w->name, '100', '60', 'OB-1', '2027-12-31'],
            ['FG-9', $w->name, '10', '', '', ''],
        ]);
        Livewire::test(DataImport::class)->set('type', 'opening_stock')->set('file', $file)->call('check')->call('import')
            ->assertSet('result', ['created' => 2, 'updated' => 0]);

        $this->assertSame(110.0, (float) Stock::where('product_id', $p->id)->value('quantity'));
        $this->assertSame('2027-12-31', Batch::where('batch_no', 'OB-1')->first()->expiry_date->toDateString());
        $this->assertSame(6500.0, $this->balance('inventory'));   // 100*60 + 10*50
        $this->assertSame(-6500.0, $this->balance('opening_equity'));
    }

    public function test_xlsx_files_are_read(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>SKU</t></si><si><t>Name</t></si><si><t>P-1</t></si><si><r><t>Rich </t></r><r><t>text</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="inlineStr"><is><t>Price</t></is></c></row>'
            .'<row r="3"><c r="A3" t="s"><v>2</v></c><c r="B3" t="s"><v>3</v></c><c r="C3"><v>12.000000001</v></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        $rows = SpreadsheetReader::read($path);
        $this->assertSame([3 => ['sku' => 'P-1', 'name' => 'Rich text', 'price' => '12']], $rows);
        @unlink($path);
    }

    public function test_every_table_is_classified_for_clearing(): void
    {
        $this->assertSame([], app(DataResetService::class)->unclassified());
    }

    public function test_clearing_transactions_keeps_master_data(): void
    {
        $p = $this->product();
        $this->receive($p, 10);
        $r = $this->retailer(['current_due' => 500]);

        $removed = app(DataResetService::class)->reset('transactions');

        $this->assertArrayHasKey('batches', $removed);
        $this->assertSame(0, Batch::count());
        $this->assertSame(1, Product::count());
        $this->assertSame(0.0, (float) $r->fresh()->current_due);
        $this->assertTrue(Account::count() > 10);
        $this->assertNotNull($this->admin->fresh());
    }

    public function test_clearing_everything_keeps_head_office_logins_and_settings(): void
    {
        $this->product();
        $r = $this->retailer();
        $retailerUser = $this->userWithRole('Retailer');
        $r->update(['user_id' => $retailerUser->id]);
        $staff = $this->userWithRole('Accountant');

        app(DataResetService::class)->reset('all', $this->admin);

        $this->assertSame(0, Product::count());
        $this->assertSame(0, Retailer::withoutGlobalScopes()->count());
        $this->assertNull(User::find($retailerUser->id));
        $this->assertNotNull(User::find($staff->id));
        $this->assertTrue($this->admin->fresh()->hasRole('Admin'));
        $this->assertTrue(Account::count() > 10);
        $this->assertFalse(\App\Models\Settings\AppSetting::flag('setup_done'));
    }

    public function test_the_clear_button_needs_password_and_confirmation(): void
    {
        $this->product();
        $c = Livewire::test(\App\Livewire\More\BackupRestore::class)
            ->set('resetPassword', 'wrong')->set('resetText', 'CLEAR')
            ->call('clearData')
            ->assertHasErrors('resetPassword');
        $this->assertSame(1, Product::count());

        $c->set('resetPassword', 'password')->set('resetText', 'clear')->call('clearData')->assertHasErrors('resetText');
    }
}
