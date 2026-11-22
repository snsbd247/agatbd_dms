<?php

namespace Tests\Feature;

use App\Livewire\Delivery\Create as DeliveryCreate;
use App\Livewire\Hr\Devices;
use App\Livewire\Returns\Receives\Create as ReturnReceiveCreate;
use App\Livewire\Sales\Orders\Create as OrderCreate;
use App\Livewire\Sales\Orders\Show as OrderShow;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Models\Dealer;
use App\Models\Hr\Attendance;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\Employee;
use App\Models\Returns\ReturnRequest;
use App\Models\Returns\ReturnRequestItem;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Models\Settings\AppSetting;
use App\Models\User;
use App\Services\Vat\VatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 6 of the ERP plan: VAT with Mushak 6.3, attendance machines pushing
 * punches (ZKTeco ADMS), and the factory / accounts / HR dashboards.
 */
class Phase6Test extends TestCase
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
    }

    private function balance(string $key): float
    {
        $id = Account::bySystemKey($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('credit') - (float) JournalLine::where('account_id', $id)->sum('debit'), 2);
    }

    /** Sell 2 × 115 (VAT 15% inside the price) to a customer and deliver it. */
    private function sell($retailer = null, float $discount = 0): SalesInvoice
    {
        $p = $this->product(['sale_price' => 115, 'purchase_price' => 70, 'vat_rate' => 15]);
        $this->receive($p, 10, 'B1', 70);
        $retailer ??= $this->retailer(['bin' => '000123456-0101']);

        $c = Livewire::test(OrderCreate::class)
            ->set('retailer_id', $retailer->id)->set('warehouse_id', $this->warehouse()->id)
            ->call('addProduct', $p->id)->set('items.0.quantity', 2);
        $c->call('save')->assertHasNoErrors();
        $order = SalesOrder::latest('id')->first();
        if ($discount > 0) {
            $order->update(['discount' => $discount, 'grand_total' => (float) $order->subtotal - $discount]);
        }
        Livewire::test(OrderShow::class, ['salesOrder' => $order])->call('approve');
        Livewire::test(DeliveryCreate::class, ['salesOrder' => $order->fresh()])->call('save')->assertHasNoErrors();

        return SalesInvoice::latest('id')->first();
    }

    // ---- VAT ------------------------------------------------------------

    public function test_with_vat_off_nothing_changes(): void
    {
        $invoice = $this->sell();

        $this->assertSame(230.0, (float) $invoice->grand_total);
        $this->assertSame(0.0, (float) $invoice->vat_amount);
        $this->assertSame(230.0, $this->balance('sales'));
        $this->assertSame(0.0, $this->balance('vat_output'));
        $this->get(route('sales.invoices.mushak', $invoice))->assertNotFound();
    }

    public function test_vat_is_split_out_of_the_price_and_posted_to_output_vat(): void
    {
        AppSetting::put([VatService::ENABLED => true, VatService::BIN => '001234567-0101']);

        $invoice = $this->sell();
        $item = $invoice->items()->first();

        $this->assertSame(230.0, (float) $invoice->grand_total, 'the customer pays the same');
        $this->assertSame(30.0, (float) $invoice->vat_amount, '230 × 15 ÷ 115');
        $this->assertSame(15.0, (float) $item->vat_rate);
        $this->assertSame(200.0, $this->balance('sales'));
        $this->assertSame(30.0, $this->balance('vat_output'));

        $this->get(route('sales.invoices.mushak', $invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('reports.vat', ['month' => today()->format('Y-m')]))->assertOk()->assertSee($invoice->number);
        $this->get(route('sales.invoices.print', $invoice))->assertOk();
    }

    public function test_vat_follows_the_order_discount(): void
    {
        AppSetting::put([VatService::ENABLED => true, VatService::BIN => '001234567-0101']);

        $invoice = $this->sell(null, 23);   // 230 − 23 = 207 charged

        $this->assertSame(207.0, (float) $invoice->grand_total);
        $this->assertSame(27.0, (float) $invoice->vat_amount, '207 × 15 ÷ 115');
        $this->assertSame(27.0, $this->balance('vat_output'));
    }

    public function test_a_dealers_customer_is_not_taxed(): void
    {
        AppSetting::put([VatService::ENABLED => true, VatService::BIN => '001234567-0101']);
        $dealer = Dealer::create(['code' => 'D1', 'name' => 'Dealer One', 'phone' => '01700000000', 'active' => true]);
        $shop = $this->retailer(['dealer_id' => $dealer->id]);

        $this->assertFalse(VatService::appliesTo($shop));
        $this->assertTrue(VatService::appliesTo($this->retailer()));
    }

    public function test_a_return_gives_the_vat_back(): void
    {
        AppSetting::put([VatService::ENABLED => true, VatService::BIN => '001234567-0101']);
        $invoice = $this->sell();
        $item = $invoice->items()->first();

        $request = ReturnRequest::create(['number' => 'RET-1', 'retailer_id' => $invoice->retailer_id, 'request_date' => today(), 'status' => 'approved']);
        ReturnRequestItem::create(['return_request_id' => $request->id, 'product_id' => $item->product_id, 'quantity' => 1, 'unit_price' => 115]);
        $c = Livewire::test(ReturnReceiveCreate::class, ['returnRequest' => $request]);
        $lines = $c->get('lines');
        $lines[0]['batch_no'] = 'RET-B1';
        Livewire::test(ReturnReceiveCreate::class, ['returnRequest' => $request->fresh()])->set('lines', $lines)->call('save')->assertHasNoErrors();

        $this->assertSame(15.0, $this->balance('vat_output'), '30 − 15 given back');
        $id = Account::bySystemKey('sales_return')->id;
        $this->assertSame(100.0, round((float) JournalLine::where('account_id', $id)->sum('debit'), 2));
    }

    public function test_the_vat_switch_needs_a_bin(): void
    {
        Livewire::test(\App\Livewire\Settings\CompanyProfile::class)
            ->set('name', 'Agro')->set('vat_enabled', true)->set('vat_bin', '')
            ->call('save')->assertHasErrors('vat_bin');

        Livewire::test(\App\Livewire\Settings\CompanyProfile::class)
            ->set('name', 'Agro')->set('vat_enabled', true)->set('vat_bin', '001234567-0101')
            ->call('save')->assertHasNoErrors();
        $this->assertTrue(VatService::enabled());
    }

    // ---- Attendance machines -------------------------------------------

    private function employee(array $attrs = []): Employee
    {
        return Employee::create($attrs + [
            'employee_code' => 'F-'.uniqid(),
            'name' => 'Worker',
            'join_date' => '2025-01-01',
            'category' => 'factory',
            'track_attendance' => true,
            'basic' => 9000,
        ]);
    }

    public function test_an_unknown_machine_is_added_inactive_and_its_punches_ignored(): void
    {
        $this->get('/iclock/cdata?SN=ABC123&options=all')->assertOk()->assertSee('OK');
        $device = AttendanceDevice::where('serial_no', 'ABC123')->firstOrFail();
        $this->assertFalse($device->active);

        $this->call('POST', '/iclock/cdata?SN=ABC123&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], "7\t2026-11-20 08:55:00\t0\t1")->assertOk();
        $this->assertSame(0, AttendancePunch::count());
    }

    public function test_an_active_machine_pushes_punches_into_attendance(): void
    {
        $device = AttendanceDevice::create(['serial_no' => 'ZK001', 'name' => 'Gate', 'active' => true]);
        $e = $this->employee(['device_user_id' => '7']);

        $this->get('/iclock/cdata?SN=ZK001&options=all')->assertOk()->assertSee('GET OPTION FROM: ZK001');

        $body = "7\t2026-11-20 08:55:00\t0\t1\t0\t0\n7\t2026-11-20 13:00:00\t1\t1\n7\t2026-11-20 17:10:00\t1\t1\n99\t2026-11-20 09:00:00\t0\t1\n";
        $this->call('POST', '/iclock/cdata?SN=ZK001&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)
            ->assertOk()->assertSee('OK: 4');
        // The same upload again is not counted twice.
        $this->call('POST', '/iclock/cdata?SN=ZK001&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertOk();

        $this->assertSame(4, AttendancePunch::count());
        $this->assertSame(4, $device->fresh()->punch_count);
        $a = Attendance::where('employee_id', $e->id)->firstOrFail();
        $this->assertSame('08:55', substr($a->in_time, 0, 5));
        $this->assertSame('17:10', substr($a->out_time, 0, 5));
        $this->assertSame('device', $a->source);

        // User 99 had no employee; once someone gets that id, the waiting punch is used.
        $this->assertSame(1, AttendancePunch::where('processed', false)->count());
        $late = $this->employee(['device_user_id' => '99']);
        Livewire::test(Devices::class)->call('processPending');
        $this->assertTrue(Attendance::where('employee_id', $late->id)->exists());

        $this->get('/iclock/getrequest?SN=ZK001')->assertOk()->assertSee('OK');
        $this->get(route('hr.devices'))->assertOk()->assertSee('ZK001');
    }

    public function test_a_day_corrected_by_hand_is_not_overwritten(): void
    {
        AttendanceDevice::create(['serial_no' => 'ZK002', 'name' => 'Gate', 'active' => true]);
        $e = $this->employee(['device_user_id' => '8']);
        app(\App\Services\Hr\AttendanceService::class)->record($e, '2026-11-20', '09:00', '17:00', 'present', 'manual', 'fixed');

        $this->call('POST', '/iclock/cdata?SN=ZK002&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], "8\t2026-11-20 10:30:00\t0\t1")->assertOk();

        $a = Attendance::where('employee_id', $e->id)->firstOrFail();
        $this->assertSame('manual', $a->source);
        $this->assertSame('09:00', substr($a->in_time, 0, 5));
    }

    // ---- Dashboards ------------------------------------------------------

    public function test_each_role_lands_on_a_dashboard_it_can_open(): void
    {
        AppSetting::put([\App\Livewire\Setup\Wizard::DONE => true]);   // not a fresh install
        $cases = [
            'Admin' => 'dashboard.dealer',
            'Factory Manager' => 'dashboard.factory',
            'Accountant' => 'dashboard.accounts',
            'HR Manager' => 'dashboard.hr',
        ];

        foreach ($cases as $role => $route) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get('/dashboard')->assertRedirect(route($route));
            $this->get(route($route))->assertOk();
        }

        $admin = $this->admin;
        foreach (['dashboard.factory', 'dashboard.accounts', 'dashboard.hr'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertSee(__('Factory'));
        }

        $this->actingAs($this->userWithRole('HR Manager'))->get(route('dashboard.factory'))->assertForbidden();
    }
}
