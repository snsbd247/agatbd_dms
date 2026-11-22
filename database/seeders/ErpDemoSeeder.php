<?php

namespace Database\Seeders;

use App\Models\Accounts\Account;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Damage\DamageRecord;
use App\Models\Damage\RepairJob;
use App\Models\Factory;
use App\Models\Hr\Department;
use App\Models\Hr\Designation;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveApplication;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Shift;
use App\Models\Hr\AttendanceDevice;
use App\Models\Product;
use App\Models\Production\Bom;
use App\Models\Production\Machine;
use App\Models\Production\MachineMaintenance;
use App\Models\Production\ProductionOrder;
use App\Models\Delivery\DeliveryNote;
use App\Models\Delivery\DeliveryNoteItem;
use App\Models\PaymentCollection;
use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceItem;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\Stock;
use App\Models\Unit;
use App\Services\NumberGeneratorService;
use App\Services\PaymentAllocationService;
use App\Services\Vat\VatService;
use App\Models\Import\ImportShipment;
use App\Models\Settings\AppSetting;
use App\Livewire\Setup\Wizard;
use App\Services\Costing\CostingService;
use App\Services\Import\ImportService;
use App\Services\Kits\AssemblyService;
use App\Support\Modules;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounts\AutoPosting;
use App\Services\Accounts\LedgerService;
use App\Services\Damage\DamageService;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\DeviceAttendanceService;
use App\Services\Hr\PayrollService;
use App\Services\InventoryService;
use App\Services\Production\ProductionService;
use App\Services\Production\StockTransferService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Twelve months of demo data for the ERP modules (phases 1–6): an agro
 * factory with formulas, raw-material buying, batch production with QC,
 * rejects and transport damage with their repairs and write-offs, machine
 * maintenance, factory and office staff with daily attendance (the last
 * days through an attendance machine), leave, advances and loans, and a
 * paid salary sheet for every finished month.
 *
 * Everything goes through the same services as the screens, with the clock
 * moved to each day, so stock, costing and the accounts all agree.
 * Safe to run on its own after the base seeders; it skips itself when the
 * demo factory already has batches.
 *
 *   php artisan db:seed --class=ErpDemoSeeder
 */
class ErpDemoSeeder extends Seeder
{
    private const MONTHS = 12;

    private User $admin;
    private Factory $factory;
    private Warehouse $raw;
    private Warehouse $fg;
    private Warehouse $damage;
    private Warehouse $depot;

    /** @var array<string, Product> */
    private array $rm = [];

    /** @var array<string, Product> */
    private array $fgProducts = [];

    /** @var array<string, Machine> */
    private array $machines = [];

    private int $seed = 7;

    public function run(): void
    {
        if (ProductionOrder::whereHas('factory', fn ($q) => $q->where('code', 'AGT-F1'))->exists()) {
            $this->command?->info('ERP demo data already present, skipping.');

            return;
        }

        if (Account::count() === 0) {
            $this->call(ChartOfAccountsSeeder::class);
        }
        $this->call(StandardUnitsSeeder::class);

        $this->admin = User::role('Admin')->whereNull('dealer_id')->orderBy('id')->firstOrFail();
        Auth::setUser($this->admin);

        $start = today()->subMonths(self::MONTHS - 1)->startOfMonth();
        $realToday = today();

        try {
            $this->travel($start->copy()->subDays(5)->setTime(10, 0));
            $this->setUpFactory();
            $this->setUpHr($start);
            app(LedgerService::class)->post(
                ['type' => 'receipt', 'entry_date' => now()->toDateString(), 'narration' => 'Capital brought in for the factory (demo)'],
                [['account' => 'bank', 'debit' => 6000000], ['account' => 'cash', 'debit' => 1000000], ['account' => 'opening_equity', 'credit' => 7000000]],
            );

            for ($m = 0; $m < self::MONTHS; $m++) {
                $monthStart = $start->copy()->addMonths($m);
                $isCurrent = $monthStart->isSameMonth($realToday);
                $this->command?->info('  '.$monthStart->format('M Y'));

                $this->month($monthStart, $isCurrent, $realToday);

                if (! $isCurrent) {
                    $this->payroll($monthStart);
                }
            }

            $this->travel($realToday->copy()->setTime(now()->hour, now()->minute));
            $this->attendanceMachine($realToday);
            $this->importCostingAndKits($realToday);
            AppSetting::put([Modules::TYPE => 'both', Wizard::DONE => true]);
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('ERP demo data seeded: production, damage & repair, maintenance, HR, attendance and payroll.');
    }

    // ---- set-up ----------------------------------------------------------------

    private function setUpFactory(): void
    {
        $kg = Unit::where('short_code', 'KG')->firstOrFail();
        $bag = Unit::where('short_code', 'BAG')->firstOrFail();
        $pcs = Unit::where('short_code', 'PCS')->firstOrFail();

        $company = Company::firstOrCreate(['code' => 'AGT'], ['name' => 'All Good Agro Tech (own production)', 'active' => true]);
        $catRaw = Category::firstOrCreate(['code' => 'RAW'], ['name' => 'Raw materials', 'active' => true]);
        $catFert = Category::firstOrCreate(['code' => 'FERT'], ['name' => 'Fertiliser', 'active' => true]);
        $catFeed = Category::firstOrCreate(['code' => 'FEED'], ['name' => 'Animal feed', 'active' => true]);

        $this->factory = Factory::firstOrCreate(['code' => 'AGT-F1'], ['name' => 'Agro Tech Factory, Gazipur', 'address' => 'BSCIC, Gazipur', 'active' => true]);
        $wh = fn (string $name, string $purpose) => Warehouse::firstOrCreate(['name' => $name], ['type' => 'central', 'factory_id' => $this->factory->id, 'purpose' => $purpose, 'active' => true]);
        $this->raw = $wh('Factory raw material store', 'raw_material');
        $this->fg = $wh('Factory finished goods store', 'finished_goods');
        $this->damage = $wh('Factory damage store', 'damage');
        $this->depot = Warehouse::where('name', 'Central Warehouse')->first()
            ?? Warehouse::firstOrCreate(['name' => 'Central Depot'], ['type' => 'central', 'purpose' => 'general', 'active' => true]);

        $product = fn (array $a) => Product::firstOrCreate(['sku' => $a['sku']], $a + ['company_id' => $company->id, 'active' => true, 'reorder_level' => 0, 'vat_rate' => 0]);

        foreach ([
            ['UREA', 'Urea', 22, $kg], ['TSP', 'TSP (triple super phosphate)', 28, $kg], ['MOP', 'MOP (potash)', 35, $kg],
            ['ZNSO4', 'Zinc sulphate', 90, $kg], ['MAIZE', 'Maize', 32, $kg], ['SOYM', 'Soybean meal', 55, $kg],
            ['BAG50', 'PP bag 50 kg', 18, $pcs], ['BAG25', 'PP bag 25 kg', 12, $pcs],
        ] as [$sku, $name, $price, $unit]) {
            $this->rm[$sku] = $product(['sku' => 'RM-'.$sku, 'name' => $name, 'type' => 'raw_material', 'category_id' => $catRaw->id, 'unit_id' => $unit->id, 'purchase_price' => $price, 'sale_price' => $price, 'reorder_level' => $unit->id === $pcs->id ? 300 : 1000]);
        }

        $fgDefs = [
            'NPK' => [['sku' => 'AGT-NPK50', 'name' => 'Agro Mix NPK 50 kg', 'category_id' => $catFert->id, 'sale_price' => 2300, 'purchase_price' => 1450, 'shelf_life_days' => 730],
                ['UREA' => [20, 2], 'TSP' => [15, 1], 'MOP' => [15, 1], 'BAG50' => [1, 0]]],
            'ZN' => [['sku' => 'AGT-ZN25', 'name' => 'Agro Zinc Plus 25 kg', 'category_id' => $catFert->id, 'sale_price' => 1350, 'purchase_price' => 820, 'shelf_life_days' => 730],
                ['TSP' => [15, 1], 'ZNSO4' => [2, 0], 'UREA' => [8, 2], 'BAG25' => [1, 0]]],
            'FEED' => [['sku' => 'AGT-LF50', 'name' => 'Agro Layer Feed 50 kg', 'category_id' => $catFeed->id, 'sale_price' => 2600, 'purchase_price' => 1900, 'shelf_life_days' => 120],
                ['MAIZE' => [30, 3], 'SOYM' => [15, 2], 'TSP' => [1, 0], 'BAG50' => [1, 0]]],
        ];

        foreach ($fgDefs as $key => [$attrs, $formula]) {
            $p = $product($attrs + ['type' => 'finished_good', 'unit_id' => $bag->id, 'reorder_level' => 100]);
            $this->fgProducts[$key] = $p;

            if (! Bom::where('product_id', $p->id)->exists()) {
                $bom = Bom::create(['product_id' => $p->id, 'version' => 1, 'name' => 'Standard', 'output_quantity' => 1, 'expected_yield' => 99,
                    'is_default' => true, 'active' => true, 'created_by' => $this->admin->id]);
                $i = 0;
                foreach ($formula as $sku => [$qty, $waste]) {
                    $bom->items()->create(['product_id' => $this->rm[$sku]->id, 'quantity' => $qty, 'wastage_percent' => $waste, 'sort' => $i++]);
                }
            }
        }

        foreach ([['MX-1', 'Fertiliser mixer 1', 'machine', 60], ['MX-2', 'Feed mixer', 'machine', 40], ['PK-1', 'Bagging & stitching line', 'line', 120]] as [$code, $name, $kind, $cap]) {
            $this->machines[$code] = Machine::firstOrCreate(['code' => $code], ['factory_id' => $this->factory->id, 'name' => $name, 'kind' => $kind, 'capacity_per_hour' => $cap, 'active' => true]);
        }
    }

    private function setUpHr(Carbon $start): void
    {
        $shift = Shift::orderBy('id')->first() ?? Shift::create(['name' => 'General (9 am – 6 pm)', 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'grace_minutes' => 10, 'weekly_off' => 'friday']);
        $dept = fn (string $code, string $name) => Department::firstOrCreate(['code' => $code], ['name' => $name]);
        $prd = $dept('PRD', 'Production');
        $adm = $dept('ADM', 'Administration');
        $acc = $dept('ACC', 'Accounts');
        $str = $dept('STR', 'Store & Logistics');
        $des = fn (string $name, Department $d) => Designation::firstOrCreate(['name' => $name], ['department_id' => $d->id])->id;

        $joined = $start->copy()->subMonths(2)->toDateString();
        $people = [
            // code, name, category, dept, designation, basic, device id
            ['AGT-O01', 'Rafiqul Islam', 'office', $adm, $des('General Manager', $adm), 45000, null],
            ['AGT-O02', 'Nasima Akter', 'office', $acc, $des('Accounts Officer', $acc), 26000, null],
            ['AGT-O03', 'Kamrul Hasan', 'office', $adm, $des('Admin Officer', $adm), 22000, null],
            ['AGT-O04', 'Sharmin Sultana', 'office', $acc, $des('Cashier', $acc), 18000, null],
            ['AGT-O05', 'Mizanur Rahman', 'office', $str, $des('Store Keeper', $str), 17000, '120'],
            ['AGT-F01', 'Abdul Karim', 'factory', $prd, $des('Production Supervisor', $prd), 24000, '101'],
            ['AGT-F02', 'Jahangir Alam', 'factory', $prd, $des('Machine Operator', $prd), 13500, '102'],
            ['AGT-F03', 'Shafiqul Islam', 'factory', $prd, $des('Machine Operator', $prd), 13500, '103'],
            ['AGT-F04', 'Rubel Mia', 'factory', $prd, $des('Helper', $prd), 10500, '104'],
            ['AGT-F05', 'Sohel Rana', 'factory', $prd, $des('Helper', $prd), 10500, '105'],
            ['AGT-F06', 'Monir Hossain', 'factory', $prd, $des('Helper', $prd), 10500, '106'],
            ['AGT-F07', 'Alamgir Hossain', 'factory', $prd, $des('QC Officer', $prd), 20000, '107'],
            ['AGT-F08', 'Babul Sheikh', 'factory', $str, $des('Loader', $str), 9800, '108'],
            ['AGT-F09', 'Hasina Begum', 'factory', $prd, $des('Packing Worker', $prd), 9800, '109'],
            ['AGT-F10', 'Rina Khatun', 'factory', $prd, $des('Packing Worker', $prd), 9800, '110'],
        ];

        foreach ($people as [$code, $name, $cat, $d, $designation, $basic, $device]) {
            Employee::firstOrCreate(['employee_code' => $code], [
                'name' => $name, 'phone' => '017'.substr(str_pad((string) crc32($code), 8, '0'), 0, 8), 'join_date' => $joined,
                'status' => 'active', 'category' => $cat, 'department_id' => $d->id, 'designation_id' => $designation,
                'factory_id' => $cat === 'factory' ? $this->factory->id : null, 'shift_id' => $shift->id,
                'device_user_id' => $device, 'track_attendance' => true, 'overtime_eligible' => $cat === 'factory',
                'payment_method' => $cat === 'factory' ? 'cash' : 'bank',
                'basic' => $basic, 'house_rent' => round($basic * 0.5), 'medical' => 750, 'conveyance' => 450, 'other_allowance' => 0,
            ]);
        }

        // A newcomer two months ago: the salary sheet pays the part month.
        Employee::firstOrCreate(['employee_code' => 'AGT-F11'], [
            'name' => 'Sabbir Ahmed', 'phone' => '01755123411', 'join_date' => today()->subMonths(1)->startOfMonth()->addDays(9)->max($start)->toDateString(),
            'status' => 'active', 'category' => 'factory', 'department_id' => $prd->id, 'designation_id' => $des('Helper', $prd),
            'factory_id' => $this->factory->id, 'shift_id' => $shift->id, 'device_user_id' => '111', 'track_attendance' => true,
            'overtime_eligible' => true, 'payment_method' => 'cash', 'basic' => 10500, 'house_rent' => 5250, 'medical' => 750, 'conveyance' => 450, 'other_allowance' => 0,
        ]);

        $holidays = ['02-21' => 'Shaheed Day', '03-26' => 'Independence Day', '04-14' => 'Pohela Boishakh', '05-01' => 'May Day',
            '08-15' => 'National Mourning Day', '12-16' => 'Victory Day', '12-25' => 'Christmas'];
        foreach ([$start->year, $start->year + 1] as $y) {
            foreach ($holidays as $md => $name) {
                Holiday::firstOrCreate(['date' => $y.'-'.$md], ['name' => $name]);
            }
        }
    }

    // ---- one month ---------------------------------------------------------------

    private function month(Carbon $monthStart, bool $isCurrent, Carbon $realToday): void
    {
        $lastDay = $isCurrent ? $realToday->copy()->subDay() : $monthStart->copy()->endOfMonth();
        $plan = $this->plannedBatches($monthStart, $isCurrent, $realToday);

        $this->buyRawMaterials($monthStart, $plan);
        $this->leave($monthStart, $isCurrent, $realToday);
        $this->attendance($monthStart, $lastDay, $isCurrent ? $realToday->copy()->subDays(6) : null);

        foreach ($plan as $i => $b) {
            $this->batch($b, $isCurrent && $i >= count($plan) - 3 ? count($plan) - 1 - $i : null);
        }

        $this->sell($monthStart, $isCurrent, $realToday);
        $this->transportDamage($monthStart, $isCurrent, $realToday);
        $this->decideOpenDamage($monthStart, $isCurrent);
        $this->maintenance($monthStart, $isCurrent, $realToday);
        $this->transferToDepot($lastDay);

        if ($monthStart->month % 4 === 1) {
            $this->loan($monthStart);
        }
    }

    /** @return array<int, array{product: Product, qty: int, date: Carbon, machine: Machine}> */
    private function plannedBatches(Carbon $monthStart, bool $isCurrent, Carbon $realToday): array
    {
        $season = in_array($monthStart->month, [10, 11, 12, 1, 2, 3], true) ? 1.25 : 0.85;   // Rabi season sells more fertiliser
        $plan = [];
        foreach ([[3, 'NPK', 'MX-1'], [8, 'FEED', 'MX-2'], [12, 'ZN', 'MX-1'], [17, 'NPK', 'MX-1'], [21, 'FEED', 'MX-2'], [25, 'NPK', 'MX-1']] as [$day, $key, $machine]) {
            $date = $monthStart->copy()->addDays($day - 1);
            if ($date->isFriday()) {
                $date->addDay();
            }
            if ($isCurrent && $date->gte($realToday)) {
                continue;
            }
            $base = ['NPK' => 170, 'ZN' => 100, 'FEED' => 130][$key];
            $fert = $key !== 'FEED';
            $plan[] = ['product' => $this->fgProducts[$key], 'qty' => (int) (round($base * ($fert ? $season : 1) * (0.85 + $this->rand(0, 30) / 100) / 10) * 10),
                'date' => $date, 'machine' => $this->machines[$machine]];
        }

        return $plan;
    }

    private function buyRawMaterials(Carbon $monthStart, array $plan): void
    {
        $need = [];
        foreach ($plan as $b) {
            $bom = Bom::with('items')->where('product_id', $b['product']->id)->where('is_default', true)->first();
            foreach ($bom->requirements($b['qty']) as $productId => $qty) {
                $need[$productId] = ($need[$productId] ?? 0) + $qty;
            }
        }
        if (! $need) {
            return;
        }

        $this->travel($monthStart->copy()->setTime(11, 0));
        $total = 0.0;
        foreach ($need as $productId => $qty) {
            $p = Product::find($productId);
            $buy = ceil($qty * 1.08 / 10) * 10;   // a little extra
            $cost = round((float) $p->purchase_price * (0.97 + $this->rand(0, 6) / 100), 2);
            app(InventoryService::class)->receiveStock(
                productId: $p->id, warehouseId: $this->raw->id, batchNo: 'RM-'.$p->sku.'-'.$monthStart->format('ym'),
                expiryDate: $monthStart->copy()->addYear()->toDateString(), costPrice: $cost, quantity: $buy, reference: $p,
            );
            $total += $buy * $cost;
        }

        app(LedgerService::class)->post(
            ['type' => 'payment', 'entry_date' => $monthStart->toDateString(), 'narration' => 'Raw material purchase for '.$monthStart->format('F Y').' (demo)'],
            [['account' => 'inventory', 'debit' => round($total, 2)], ['account' => 'bank', 'credit' => round($total, 2)]],
        );
    }

    /**
     * One batch, through the same steps as the screens. $stopAt leaves the
     * newest batches of the current month part-way: 0 planned, 1 issued, 2 at QC.
     */
    private function batch(array $b, ?int $stopAt): void
    {
        $svc = app(ProductionService::class);
        $this->travel($b['date']->copy()->setTime(8, 30));
        $order = $svc->create([
            'factory_id' => $this->factory->id, 'product_id' => $b['product']->id,
            'bom_id' => Bom::where('product_id', $b['product']->id)->where('is_default', true)->value('id'),
            'planned_quantity' => $b['qty'], 'mfg_date' => $b['date']->toDateString(), 'machine_id' => $b['machine']->id,
        ]);
        if ($stopAt === 0) {
            return;
        }

        $this->travel($b['date']->copy()->setTime(9, 15));
        $svc->issue($order->fresh());
        if ($stopAt === 1) {
            return;
        }

        $this->travel($b['date']->copy()->setTime(14, 0));
        // Now and then the first check fails and the batch is corrected.
        if ($this->rand(1, 8) === 1) {
            $svc->qc($order->fresh(), 'fail', 'Moisture 14.5% (limit 12%)', 'Dried another hour');
            $this->travel($b['date']->copy()->setTime(15, 30));
        }
        $svc->qc($order->fresh(), 'pass', 'Moisture 10.8%, N-P-K within ±0.5%', null);
        if ($stopAt === 2) {
            return;
        }

        $this->travel($b['date']->copy()->setTime(17, 0));
        $rejected = (float) $this->rand(0, (int) ceil($b['qty'] * 0.02));
        $good = round($b['qty'] * ($this->rand(970, 995) / 1000) - $rejected);
        $svc->complete($order->fresh(), $good, $rejected, round($b['qty'] * 18, 2), round($b['qty'] * 12, 2));
    }

    private function transportDamage(Carbon $monthStart, bool $isCurrent, Carbon $realToday): void
    {
        if ($monthStart->month % 2 === 0) {
            return;
        }
        $date = $monthStart->copy()->addDays(19);
        if ($isCurrent && $date->gte($realToday)) {
            return;
        }
        $this->travel($date->copy()->setTime(12, 0));
        $batch = Batch::where('warehouse_id', $this->depot->id)->where('quantity_available', '>=', 10)
            ->whereIn('product_id', collect($this->fgProducts)->pluck('id'))->orderByDesc('id')->first();
        if (! $batch) {
            return;
        }

        $svc = app(DamageService::class);
        $record = $svc->report($batch->id, $this->rand(3, 8), 'transport', 'Bags torn while unloading from the truck', $date->toDateString());
        if ($isCurrent) {
            return;   // left waiting for approval
        }
        $svc->approve($record->fresh());
    }

    /** Decide everything still open in the damage store, except in the current month. */
    private function decideOpenDamage(Carbon $monthStart, bool $isCurrent): void
    {
        if ($isCurrent) {
            return;
        }
        $svc = app(DamageService::class);
        $date = $monthStart->copy()->endOfMonth()->subDays(2);
        $this->travel($date->copy()->setTime(11, 0));

        foreach (DamageRecord::where('status', 'approved')->where('quantity_open', '>', 0)->get() as $i => $record) {
            $qty = (float) $record->quantity_open;
            $choice = $record->source === 'transport' ? 'repair' : ['reprocess', 'discount_sale', 'destroy'][($monthStart->month + $i) % 3];

            if ($choice === 'discount_sale') {
                $svc->decide($record->fresh(), 'discount_sale', $qty, ['target_warehouse_id' => $this->depot->id, 'recovery_price' => round((float) $record->unit_cost * 0.6, 2), 'notes' => 'Sold as second grade']);
            } elseif ($choice === 'destroy') {
                $svc->decide($record->fresh(), 'destroy', $qty, ['notes' => 'Hardened, not usable']);
            } else {
                $svc->decide($record->fresh(), $choice, $qty, [
                    'mode' => $choice === 'repair' ? 'outsourced' : 'in_house', 'vendor' => $choice === 'repair' ? 'Gazipur Bag House' : null,
                    'expected_date' => $date->copy()->addDays(3)->toDateString(), 'estimated_cost' => $qty * 25,
                    'target_warehouse_id' => $choice === 'repair' ? $this->depot->id : $this->fg->id,
                ]);
                $job = RepairJob::where('status', 'open')->latest('id')->first();
                $this->travel($date->copy()->addDay()->setTime(16, 0));
                $repaired = max(0, $qty - ($this->rand(1, 4) === 1 ? 1 : 0));
                $svc->completeRepair($job, $repaired, round($qty * 25, 2), Account::bySystemKey('cash')->id, null);
                $this->travel($date->copy()->setTime(11, 0));
            }
        }
    }

    private function maintenance(Carbon $monthStart, bool $isCurrent, Carbon $realToday): void
    {
        foreach ($this->machines as $code => $machine) {
            $offset = ['MX-1' => 0, 'MX-2' => 1, 'PK-1' => 2][$code];
            $date = $monthStart->copy()->addDays(9 + $offset);
            if ($isCurrent && $date->gte($realToday)) {
                continue;
            }
            $breakdown = $code === 'MX-1' && $monthStart->month === 6;
            if (! $breakdown && ($monthStart->month + $offset) % 3 !== 0) {
                continue;
            }
            $this->travel($date->copy()->setTime(10, 0));
            $m = MachineMaintenance::create([
                'machine_id' => $machine->id,
                'type' => $breakdown ? 'breakdown' : 'preventive',
                'maintenance_date' => $date->toDateString(),
                'description' => $breakdown ? 'Gearbox bearing broke; replaced bearing and seal' : 'Quarterly service: greasing, belt check, motor cleaning',
                'downtime_hours' => $breakdown ? 9 : 3,
                'vendor' => $breakdown ? 'Dhaka Engineering Works' : null,
                'cost' => $breakdown ? 18500 : 3500 + $this->rand(0, 30) * 100,
                'paid_from_account_id' => Account::bySystemKey('cash')->id,
                'next_due_date' => $breakdown ? null : $date->copy()->addDays(90)->toDateString(),
                'created_by' => $this->admin->id,
            ]);
            app(AutoPosting::class)->machineMaintenance($m);
        }
    }

    private function transferToDepot(Carbon $date): void
    {
        $batches = Batch::where('warehouse_id', $this->fg->id)->where('quantity_available', '>', 0)->get();
        if ($batches->isEmpty()) {
            return;
        }
        $this->travel($date->copy()->setTime(16, 30));
        // Most of the month's output goes to the depot; some stays at the factory.
        app(StockTransferService::class)->create($this->fg->id, $this->depot->id, $date->toDateString(),
            $batches->map(fn ($b) => ['batch_id' => $b->id, 'quantity' => floor((float) $b->quantity_available * 0.8)])->all(),
            'Monthly dispatch to depot');
    }

    /**
     * Sell the factory's products from the depot to head-office customers on
     * the 10th and the 24th, and collect most of it about a week later.
     */
    private function sell(Carbon $monthStart, bool $isCurrent, Carbon $realToday): void
    {
        $customers = Retailer::whereNull('dealer_id')->whereNull('account_dealer_id')->where('active', true)->orderBy('id')->limit(5)->get();
        if ($customers->isEmpty()) {
            return;
        }
        $numbers = app(NumberGeneratorService::class);

        foreach ([10, 24] as $round => $day) {
            $date = $monthStart->copy()->addDays($day - 1);
            if ($isCurrent && $date->gte($realToday)) {
                continue;
            }
            $this->travel($date->copy()->setTime(11, 0));

            foreach ($customers as $ci => $customer) {
                $lines = [];
                foreach ($this->fgProducts as $p) {
                    $available = (float) Stock::where('product_id', $p->id)->where('warehouse_id', $this->depot->id)->value('quantity');
                    $qty = floor($available * 0.85 / ($customers->count() * (2 - $round)) * (0.8 + $this->rand(0, 40) / 100));
                    if ($qty >= 1) {
                        $lines[] = ['product' => $p, 'qty' => min($qty, $available)];
                    }
                }
                if (! $lines) {
                    continue;
                }

                $subtotal = array_sum(array_map(fn ($l) => $l['qty'] * (float) $l['product']->sale_price, $lines));
                $order = SalesOrder::create([
                    'number' => $numbers->generate('sales_order'), 'order_type' => 'retailer', 'retailer_id' => $customer->id,
                    'representative_id' => $customer->representative_id, 'warehouse_id' => $this->depot->id, 'status' => 'approved',
                    'order_date' => $date->toDateString(), 'subtotal' => $subtotal, 'discount' => 0, 'grand_total' => $subtotal,
                    'approved_by' => $this->admin->id, 'approved_at' => now(),
                ]);
                foreach ($lines as $l) {
                    SalesOrderItem::create(['sales_order_id' => $order->id, 'product_id' => $l['product']->id, 'quantity' => $l['qty'], 'unit_price' => $l['product']->sale_price]);
                }
                $invoice = $this->deliver($order->fresh('items'), $date);

                // Collected about a week later (the newest ones stay due).
                $payDay = $date->copy()->addDays(6 + $ci);
                if ($payDay->lt($realToday)) {
                    $this->travel($payDay->copy()->setTime(15, 0));
                    $amount = round((float) $invoice->grand_total * ($this->rand(70, 100) / 100), 2);
                    $collection = PaymentCollection::create([
                        'number' => $numbers->generate('collection'), 'retailer_id' => $customer->id, 'collected_by' => $this->admin->id,
                        'collection_date' => $payDay->toDateString(), 'amount' => $amount, 'method' => 'bank', 'reference_no' => 'DEMO-'.$invoice->number,
                    ]);
                    app(PaymentAllocationService::class)->allocate($customer->fresh(), $amount, $collection);
                    app(AutoPosting::class)->collection($collection);
                    $this->travel($date->copy()->setTime(11, 0));
                }
            }
        }
    }

    /** What Delivery → Create does: stock out by expiry, invoice, customer due, accounts. */
    private function deliver(SalesOrder $order, Carbon $date): SalesInvoice
    {
        $numbers = app(NumberGeneratorService::class);
        $note = DeliveryNote::create(['number' => $numbers->generate('delivery_note'), 'sales_order_id' => $order->id, 'delivery_type' => 'dealer',
            'delivered_by' => $this->admin->id, 'delivery_date' => $date->toDateString(), 'status' => 'delivered']);

        $total = 0.0;
        $lines = [];
        foreach ($order->items as $item) {
            foreach (app(InventoryService::class)->consumeStock($item->product_id, $order->warehouse_id, (float) $item->quantity, $note) as $split) {
                DeliveryNoteItem::create(['delivery_note_id' => $note->id, 'sales_order_item_id' => $item->id, 'product_id' => $item->product_id,
                    'batch_id' => $split['batch_id'], 'quantity' => $split['quantity']]);
                $lineTotal = $split['quantity'] * (float) $item->unit_price;
                $total += $lineTotal;
                $lines[] = ['product_id' => $item->product_id, 'batch_id' => $split['batch_id'], 'quantity' => $split['quantity'], 'unit_price' => $item->unit_price, 'line_total' => $lineTotal];
            }
            $item->update(['delivered_quantity' => $item->quantity]);
        }

        $customer = Retailer::find($order->retailer_id);
        [$taxed, $vat] = VatService::applyToLines($lines, 0, $customer);
        $invoice = SalesInvoice::create(['number' => $numbers->generate('sales_invoice'), 'sales_order_id' => $order->id, 'retailer_id' => $order->retailer_id,
            'invoice_date' => $date->toDateString(), 'subtotal' => $total, 'discount' => 0, 'grand_total' => $total, 'vat_amount' => $vat,
            'paid_amount' => 0, 'due_amount' => $total, 'status' => 'unpaid']);
        foreach ($taxed as $line) {
            SalesInvoiceItem::create(['sales_invoice_id' => $invoice->id, ...$line]);
        }

        $customer->increment('current_due', $total);
        $customer->ledgerEntries()->create(['type' => 'invoice', 'reference_type' => $invoice->getMorphClass(), 'reference_id' => $invoice->getKey(),
            'debit' => $total, 'credit' => 0, 'balance_after' => $customer->fresh()->current_due, 'entry_date' => $date->toDateString()]);
        $order->update(['status' => 'completed']);
        app(AutoPosting::class)->salesInvoice($invoice);

        return $invoice;
    }

    // ---- people ----------------------------------------------------------------

    private function leave(Carbon $monthStart, bool $isCurrent, Carbon $realToday): void
    {
        $staff = Employee::where('employee_code', 'like', 'AGT-%')->orderBy('id')->get();
        $types = LeaveType::pluck('id', 'code');

        foreach ([[5, 'CL', 1], [14, 'SL', 2], [22, 'CL', 1]] as $k => [$day, $code, $days]) {
            $from = $monthStart->copy()->addDays($day - 1);
            while ($from->isFriday() || Holiday::whereDate('date', $from->toDateString())->exists()) {
                $from->addDay();
            }
            $to = $from->copy()->addDays($days - 1);
            $employee = $staff[($monthStart->month * 3 + $k) % $staff->count()];
            if ($employee->join_date->gt($from)) {
                continue;
            }
            $pending = $isCurrent && $from->gte($realToday->copy()->subDays(3));
            $this->travel($from->copy()->subDays(2)->setTime(10, 0));
            LeaveApplication::create([
                'employee_id' => $employee->id, 'leave_type_id' => $types[$code] ?? $types->first(),
                'from_date' => $from->toDateString(), 'to_date' => $to->toDateString(), 'days' => $days,
                'reason' => $code === 'SL' ? 'Fever' : 'Family matter',
                'status' => $pending ? 'pending' : 'approved',
                'decided_by' => $pending ? null : $this->admin->id, 'decided_at' => $pending ? null : now(),
            ]);
        }
    }

    /** Daily attendance from $from to $to; from $deviceFrom on it comes through the machine instead. */
    private function attendance(Carbon $from, Carbon $to, ?Carbon $deviceFrom): void
    {
        $svc = app(AttendanceService::class);
        $staff = Employee::where('employee_code', 'like', 'AGT-%')->where('track_attendance', true)->get();
        $holidays = Holiday::whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $leaves = LeaveApplication::where('status', 'approved')->whereDate('to_date', '>=', $from)->whereDate('from_date', '<=', $to)->get();

        foreach (CarbonPeriod::create($from, $to) as $day) {
            if ($day->isFriday() || in_array($day->toDateString(), $holidays, true) || ($deviceFrom && $day->gte($deviceFrom))) {
                continue;
            }
            foreach ($staff as $e) {
                if ($e->join_date->gt($day) || $leaves->contains(fn ($l) => $l->employee_id === $e->id && $day->between($l->from_date, $l->to_date))) {
                    continue;
                }
                [$in, $out] = $this->times($e, $day);
                if ($in === null) {
                    continue;   // absent
                }
                $svc->record($e, $day->toDateString(), $in, $out, null, $e->device_user_id ? 'device' : 'manual');
            }
        }
    }

    /** @return array{0: ?string, 1: ?string} */
    private function times(Employee $e, Carbon $day): array
    {
        $r = $this->rand(1, 100);
        if ($r <= 3) {
            return [null, null];
        }
        $inMin = $r <= 13 ? $this->rand(11, 45) : -$this->rand(0, 15);   // minutes after 9:00
        $outMin = $e->category === 'factory' && $this->rand(1, 3) === 1 ? $this->rand(30, 120) : $this->rand(0, 25);

        return [$day->copy()->setTime(9, 0)->addMinutes($inMin)->format('H:i'), $day->copy()->setTime(18, 0)->addMinutes($outMin)->format('H:i')];
    }

    private function loan(Carbon $monthStart): void
    {
        $date = $monthStart->copy()->addDays(6);
        $this->travel($date->copy()->setTime(12, 0));
        $staff = Employee::where('employee_code', 'like', 'AGT-F%')->orderBy('id')->get();
        $e = $staff[$monthStart->month % $staff->count()];
        $isLoan = $monthStart->month % 2 === 1;
        $loan = EmployeeLoan::create([
            'employee_id' => $e->id, 'type' => $isLoan ? 'loan' : 'advance', 'amount' => $isLoan ? 15000 : 3000,
            'installment' => $isLoan ? 2500 : 0, 'issue_date' => $date->toDateString(), 'paid_from' => 'cash',
            'note' => $isLoan ? 'Medical treatment for mother' : 'Salary advance', 'recovered' => 0, 'status' => 'open',
        ]);
        app(AutoPosting::class)->loanIssued($loan);
    }

    private function payroll(Carbon $monthStart): void
    {
        $svc = app(PayrollService::class);
        $this->travel($monthStart->copy()->addMonth()->addDays(1)->setTime(11, 0));
        $run = $svc->generate($monthStart->format('Y-m'));
        $svc->approve($run->fresh());
        $this->travel($monthStart->copy()->addMonth()->addDays(4)->setTime(15, 0));
        $svc->pay($run->fresh(), 'bank');
    }

    /**
     * Phase 7: one import of potash received at landed cost, cost sheets for
     * the factory's products (the dealer / retailer / MRP prices in use), and
     * a seasonal combo kit plus a repacked 5 kg fertiliser pack.
     */
    private function importCostingAndKits(Carbon $realToday): void
    {
        $bank = Account::bySystemKey('bank')->id;
        $supplier = Company::firstOrCreate(['code' => 'BLR-K'], ['name' => 'Belaruskali Trading (demo)', 'country' => 'Belarus', 'currency' => 'USD', 'active' => true]);
        $imports = app(ImportService::class);

        $start = $realToday->copy()->subMonths(4)->startOfMonth()->addDays(2);
        $this->travel($start->copy()->setTime(11, 0));
        $s = ImportShipment::create(['number' => app(NumberGeneratorService::class)->generate('import_shipment'), 'company_id' => $supplier->id,
            'warehouse_id' => $this->raw->id, 'proforma_no' => 'PI-BK-2026-114', 'proforma_date' => $start->toDateString(), 'currency' => 'USD',
            'exchange_rate' => 121.5, 'allocation_basis' => 'value', 'status' => 'draft', 'created_by' => $this->admin->id]);
        $s->items()->create(['product_id' => $this->rm['MOP']->id, 'quantity' => 20000, 'unit_price_fc' => 0.27, 'weight_kg' => 20000]);
        $s->items()->create(['product_id' => $this->rm['ZNSO4']->id, 'quantity' => 2000, 'unit_price_fc' => 0.68, 'weight_kg' => 2000]);
        $imports->openLc($s, 'LC-0457-26', $start->toDateString(), $bank, 140000);

        $this->travel($start->copy()->addDays(9)->setTime(12, 0));
        $imports->shipped($s->fresh(), 'MSCU-4471209', today()->toDateString(), today()->addDays(24)->toDateString());
        $this->travel($start->copy()->addDays(33)->setTime(10, 0));
        $imports->arrived($s->fresh(), today()->toDateString(), 'Chattogram');
        foreach ([['cd', 33100], ['rd', 0], ['vat', 116900], ['at', 38900], ['ait', 32400], ['cnf', 18500], ['port', 12600], ['transport', 24000], ['bank', 9800]] as [$type, $amount]) {
            $amount > 0 && $imports->addCost($s->fresh(), $type, $amount, today()->toDateString(), $bank, null);
        }
        $this->travel($start->copy()->addDays(36)->setTime(15, 0));
        $imports->receive($s->fresh(), today()->toDateString(), []);

        // A second import still on the water.
        $this->travel($realToday->copy()->subDays(12)->setTime(11, 0));
        $s2 = ImportShipment::create(['number' => app(NumberGeneratorService::class)->generate('import_shipment'), 'company_id' => $supplier->id,
            'warehouse_id' => $this->raw->id, 'proforma_no' => 'PI-BK-2026-139', 'proforma_date' => today()->toDateString(), 'currency' => 'USD',
            'exchange_rate' => 122.0, 'allocation_basis' => 'value', 'status' => 'draft', 'created_by' => $this->admin->id]);
        $s2->items()->create(['product_id' => $this->rm['MOP']->id, 'quantity' => 25000, 'unit_price_fc' => 0.28, 'weight_kg' => 25000]);
        $imports->openLc($s2, 'LC-0512-26', today()->toDateString(), $bank, 170000);
        $this->travel($realToday->copy()->subDays(4)->setTime(11, 0));
        $imports->shipped($s2->fresh(), 'MSCU-5530871', today()->toDateString(), today()->addDays(22)->toDateString());

        // Cost sheets for the factory's products, approved two months ago.
        $this->travel($realToday->copy()->subMonths(2)->startOfMonth()->setTime(10, 0));
        $costing = app(CostingService::class);
        foreach ($this->fgProducts as $key => $p) {
            $m = $costing->materials($p, 'formula');
            $c = $costing->compute($m['lines'], $costing->defaultOverheads('formula'), 8, 6, 8);
            $sheet = $costing->save($p, ['basis' => 'formula', 'bom_id' => $m['bom_id'], 'materials' => $m['lines'], 'overheads' => $costing->defaultOverheads('formula'),
                'company_margin' => 8, 'dealer_margin' => 6, 'retailer_margin' => 8, 'dealer_price' => $c['dealer_price'], 'retailer_price' => $c['retailer_price'], 'mrp' => $c['mrp'],
                'effective_from' => today()->toDateString(), 'first_supply_date' => today()->toDateString(), 'notes' => null]);
            $costing->approve($sheet);
        }
        // A new version waiting for approval.
        $p = $this->fgProducts['NPK'];
        $m = $costing->materials($p, 'formula');
        $this->travel($realToday->copy()->subDays(2)->setTime(16, 0));
        $c = $costing->compute($m['lines'], $costing->defaultOverheads('formula'), 9, 6, 8);
        $costing->save($p, ['basis' => 'formula', 'bom_id' => $m['bom_id'], 'materials' => $m['lines'], 'overheads' => $costing->defaultOverheads('formula'),
            'company_margin' => 9, 'dealer_margin' => 6, 'retailer_margin' => 8, 'dealer_price' => $c['dealer_price'], 'retailer_price' => $c['retailer_price'], 'mrp' => $c['mrp'],
            'effective_from' => $realToday->copy()->addDays(10)->toDateString(), 'first_supply_date' => null, 'notes' => 'Potash price went up']);

        // Kits and repacking in the depot.
        $pack5 = Product::firstOrCreate(['sku' => 'AGT-NPK5'], ['name' => 'Agro Mix NPK 5 kg pack', 'type' => 'trading', 'company_id' => $p->company_id,
            'category_id' => $p->category_id, 'unit_id' => Unit::where('short_code', 'PCS')->value('id'), 'purchase_price' => 160, 'sale_price' => 250, 'active' => true, 'reorder_level' => 0, 'vat_rate' => 0]);
        $pouch = Product::firstOrCreate(['sku' => 'RM-POUCH5'], ['name' => 'Printed pouch 5 kg', 'type' => 'packaging', 'company_id' => $p->company_id,
            'category_id' => $this->rm['BAG50']->category_id, 'unit_id' => Unit::where('short_code', 'PCS')->value('id'), 'purchase_price' => 6, 'sale_price' => 6, 'active' => true, 'reorder_level' => 0, 'vat_rate' => 0]);
        $combo = Product::firstOrCreate(['sku' => 'AGT-RABI-KIT'], ['name' => 'Rabi season combo (NPK + Zinc)', 'type' => 'trading', 'company_id' => $p->company_id,
            'category_id' => $p->category_id, 'unit_id' => Unit::where('short_code', 'PCS')->value('id'), 'purchase_price' => 3000, 'sale_price' => 3500, 'active' => true, 'reorder_level' => 0, 'vat_rate' => 0]);

        // 10 packs of 5 kg from one 50 kg bag.
        $repack = Bom::create(['product_id' => $pack5->id, 'kind' => 'repack', 'version' => 1, 'name' => '50 kg bag into 10 × 5 kg', 'output_quantity' => 10,
            'expected_yield' => 100, 'is_default' => true, 'active' => true, 'created_by' => $this->admin->id]);
        $repack->items()->create(['product_id' => $this->fgProducts['NPK']->id, 'quantity' => 1, 'wastage_percent' => 0, 'sort' => 0]);
        $repack->items()->create(['product_id' => $pouch->id, 'quantity' => 10, 'wastage_percent' => 2, 'sort' => 1]);
        $kit = Bom::create(['product_id' => $combo->id, 'kind' => 'kit', 'version' => 1, 'name' => 'One NPK bag + one zinc bag', 'output_quantity' => 1,
            'expected_yield' => 100, 'is_default' => true, 'active' => true, 'created_by' => $this->admin->id]);
        $kit->items()->create(['product_id' => $this->fgProducts['NPK']->id, 'quantity' => 1, 'wastage_percent' => 0, 'sort' => 0]);
        $kit->items()->create(['product_id' => $this->fgProducts['ZN']->id, 'quantity' => 1, 'wastage_percent' => 0, 'sort' => 1]);

        $this->travel($realToday->copy()->subDays(20)->setTime(10, 0));
        app(InventoryService::class)->receiveStock(productId: $pouch->id, warehouseId: $this->depot->id, batchNo: 'POUCH-'.today()->format('ym'),
            expiryDate: null, costPrice: 6, quantity: 500, reference: $pouch);
        app(LedgerService::class)->post(['type' => 'payment', 'entry_date' => today()->toDateString(), 'narration' => 'Printed pouches (demo)'],
            [['account' => 'inventory', 'debit' => 3000], ['account' => 'cash', 'credit' => 3000]]);

        $assembly = app(AssemblyService::class);
        $cash = Account::bySystemKey('cash')->id;
        foreach ([[$repack, 100, 400], [$kit, 20, 200]] as [$bom, $qty, $labour]) {
            try {
                $assembly->assemble($bom, $qty, $this->depot->id, today()->toDateString(), $labour, $cash);
            } catch (\RuntimeException $e) {
                $this->command?->warn('  skipped '.$bom->kind.': '.$e->getMessage());
            }
            $this->travel(now()->addDays(3));
        }
    }

    /** A machine at the factory gate that sent the last few days' punches. */
    private function attendanceMachine(Carbon $realToday): void
    {
        $device = AttendanceDevice::firstOrCreate(['serial_no' => 'AGT-DEMO-ZK01'], ['name' => 'Factory gate (demo)', 'location' => 'Gazipur factory', 'active' => true]);
        $staff = Employee::whereNotNull('device_user_id')->where('employee_code', 'like', 'AGT-%')->get();
        $lines = [];

        foreach (CarbonPeriod::create($realToday->copy()->subDays(6), $realToday) as $day) {
            if ($day->isFriday() || Holiday::whereDate('date', $day->toDateString())->exists()) {
                continue;
            }
            foreach ($staff as $e) {
                if ($e->join_date->gt($day)) {
                    continue;
                }
                [$in, $out] = $this->times($e, $day);
                if ($in === null) {
                    continue;
                }
                $lines[] = $e->device_user_id."\t".$day->toDateString().' '.$in.':00'."\t0\t1";
                if (! $day->isSameDay($realToday) || now()->hour >= 18) {
                    $lines[] = $e->device_user_id."\t".$day->toDateString().' '.$out.':00'."\t1\t1";
                }
            }
        }
        // One punch from a user id no one has yet, to show "waiting".
        $lines[] = "130\t".$realToday->copy()->subDay()->toDateString()." 08:58:00\t0\t1";

        app(DeviceAttendanceService::class)->ingest($device, implode("\n", $lines));
        $device->update(['last_seen_at' => now(), 'last_ip' => '103.4.145.10']);
    }

    // ---- helpers ---------------------------------------------------------------

    private function travel(Carbon $at): void
    {
        Carbon::setTestNow($at);
    }

    /** Repeatable pseudo-random numbers, so every run gives the same data. */
    private function rand(int $min, int $max): int
    {
        $this->seed = ($this->seed * 1103515245 + 12345) % 2147483648;

        return $min + $this->seed % ($max - $min + 1);
    }
}
