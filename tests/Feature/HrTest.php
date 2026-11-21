<?php

namespace Tests\Feature;

use App\Livewire\Hr\Attendance as AttendanceScreen;
use App\Livewire\Hr\Employees\Index as EmployeesIndex;
use App\Livewire\Hr\Leaves;
use App\Livewire\Hr\Loans;
use App\Livewire\Hr\Payroll\Index as PayrollIndex;
use App\Livewire\Hr\Payroll\Show as PayrollShow;
use App\Livewire\Payroll\Entries\Create as OldPayrollCreate;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveApplication;
use App\Models\Hr\LeaveType;
use App\Models\Hr\PayrollRun;
use App\Models\Hr\Shift;
use App\Models\Payroll\Advance;
use App\Models\Representative;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\PayrollService;
use App\Services\Hr\SettlementService;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3 of the ERP plan: employees, attendance, leave, advances and loans,
 * the salary sheet (Bangladesh rules) and the final settlement.
 */
class HrTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = '2026-09';   // 30 days, Fridays 4, 11, 18, 25

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->userWithRole('Admin'));
    }

    private function worker(array $attrs = []): Employee
    {
        return Employee::create($attrs + [
            'employee_code' => 'F-'.uniqid(),
            'name' => 'Factory worker',
            'join_date' => '2025-01-01',
            'category' => 'factory',
            'shift_id' => Shift::orderBy('id')->value('id'),
            'track_attendance' => true,
            'overtime_eligible' => true,
            'basic' => 9000, 'house_rent' => 4500, 'medical' => 750, 'conveyance' => 450, 'other_allowance' => 0,
        ]);
    }

    public function test_attendance_rules_and_import(): void
    {
        $e = $this->worker(['employee_code' => 'EMP-0001', 'device_user_id' => '15']);
        $svc = app(AttendanceService::class);

        $this->assertSame('present', $svc->record($e, '2026-09-01', '09:05', '18:00')->status, 'within the 10 minutes grace');
        $this->assertSame('late', $svc->record($e, '2026-09-02', '09:30', '18:00')->status);
        $this->assertSame('half_day', $svc->record($e, '2026-09-03', '09:00', '12:00')->status);
        $this->assertSame(120, $svc->record($e, '2026-09-05', '09:00', '20:00')->overtime_minutes);
        $svc->record($e, '2026-09-01', '08:58', '18:00');   // the same day again updates it
        $this->assertSame(1, Attendance::where('employee_id', $e->id)->whereDate('date', '2026-09-01')->count());

        // Day rows by employee code, and a ZKTeco punch log by device id.
        $result = $svc->import("code,date,in,out\nEMP-0001,2026-09-06,09:00,18:00\nNOBODY,2026-09-06,09:00,18:00\n");
        $this->assertSame(1, $result['saved']);
        $this->assertCount(1, $result['skipped']);

        $result = $svc->import("15,2026-09-07 08:51:10\n15,2026-09-07 13:02:00\n15,2026-09-07 19:30:44\n");
        $this->assertSame(1, $result['saved']);
        $day = Attendance::where('employee_id', $e->id)->whereDate('date', '2026-09-07')->first();
        $this->assertSame('08:51', substr($day->in_time, 0, 5), 'first punch is the in time');
        $this->assertSame('19:30', substr($day->out_time, 0, 5), 'last punch is the out time');
        $this->assertSame(90, $day->overtime_minutes);

        Livewire::test(AttendanceScreen::class, ['tab' => 'monthly', 'month' => self::PERIOD])->assertOk()->assertSee($e->name);
    }

    public function test_salary_sheet_follows_attendance_leave_overtime_advances_and_loans(): void
    {
        Holiday::create(['date' => '2026-09-16', 'name' => 'Test holiday']);
        $e = $this->worker();
        $svc = app(AttendanceService::class);

        $absent = ['2026-09-08', '2026-09-09'];
        $casual = '2026-09-10';
        $unpaid = '2026-09-14';
        foreach (CarbonPeriod::create('2026-09-01', '2026-09-30') as $d) {
            $ds = $d->toDateString();
            if ($d->isFriday() || $ds === '2026-09-16' || in_array($ds, [...$absent, $casual, $unpaid], true)) {
                continue;
            }
            $svc->record($e, $ds, $ds === '2026-09-02' ? '09:30' : '09:00', $ds === '2026-09-03' ? '20:00' : '18:00');
        }
        foreach ([[$casual, 'CL'], [$unpaid, 'LWP']] as [$date, $code]) {
            LeaveApplication::create(['employee_id' => $e->id, 'leave_type_id' => LeaveType::where('code', $code)->value('id'),
                'from_date' => $date, 'to_date' => $date, 'days' => 1, 'status' => 'approved']);
        }
        EmployeeLoan::create(['employee_id' => $e->id, 'type' => 'advance', 'amount' => 1000, 'issue_date' => '2026-09-05', 'status' => 'open']);
        EmployeeLoan::create(['employee_id' => $e->id, 'type' => 'loan', 'amount' => 5000, 'installment' => 1500, 'issue_date' => '2026-08-01', 'status' => 'open']);

        // Joins mid-month, attendance not tracked: half a month's pay.
        $newcomer = Employee::create(['employee_code' => 'O-1', 'name' => 'Newcomer', 'join_date' => '2026-09-16', 'category' => 'office',
            'track_attendance' => false, 'basic' => 20000]);

        Livewire::test(PayrollIndex::class)->set('period', self::PERIOD)->call('generate')->assertHasNoErrors();
        $run = PayrollRun::firstOrFail();
        $slip = $run->payslips()->where('employee_id', $e->id)->first();

        $this->assertEquals(25, (float) $slip->working_days);       // 30 − 4 Fridays − 1 holiday
        $this->assertEquals(21, (float) $slip->present_days);
        $this->assertEquals(1, (float) $slip->paid_leave_days);
        $this->assertEquals(1, (float) $slip->unpaid_leave_days);
        $this->assertEquals(2, (float) $slip->absent_days);
        $this->assertEquals(900, (float) $slip->absent_deduction);  // (2 absent + 1 unpaid) × 9000 ÷ 30
        $this->assertEquals(2, (float) $slip->overtime_hours);
        $this->assertEquals(173.08, (float) $slip->overtime_amount); // 2 h × 9000 ÷ 208 × 2
        $this->assertEquals(1000, (float) $slip->advance_deduction);
        $this->assertEquals(1500, (float) $slip->loan_deduction);
        $this->assertEquals(14873.08, (float) $slip->gross);
        $this->assertEquals(11473.08, (float) $slip->net);

        $new = $run->payslips()->where('employee_id', $newcomer->id)->first();
        $this->assertEquals(15, (float) $new->payable_days);
        $this->assertEquals(10000, (float) $new->basic);
        $this->assertEquals(0, (float) $new->absent_deduction);

        // A bonus added on the draft, then approve and pay.
        Livewire::test(PayrollShow::class, ['run' => $run])
            ->call('edit', $slip->id)->set('bonus', '500')->call('saveSlip')->assertHasNoErrors()
            ->call('approve')->set('pay_method', 'bank')->call('pay')->assertHasNoErrors();

        $this->assertSame('paid', $run->fresh()->status);
        $this->assertEquals(11973.08, (float) $slip->fresh()->net);
        $this->assertSame('closed', EmployeeLoan::where('type', 'advance')->first()->status);
        $this->assertEquals(1500, (float) EmployeeLoan::where('type', 'loan')->first()->recovered);

        $voucher = JournalEntry::where('source_type', $run->getMorphClass())->where('source_id', $run->id)->firstOrFail();
        $this->assertEquals((float) $voucher->lines->sum('debit'), (float) $voucher->lines->sum('credit'));
        $bank = $voucher->lines->firstWhere('account_id', Account::bySystemKey('bank')->id);
        $this->assertEquals(11973.08 + 10000, (float) $bank->credit);

        foreach ([route('hr.payroll.pdf', $run), route('hr.payslips.pdf', $run), route('hr.payslip.pdf', $slip)] as $url) {
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }

        $this->expectException(\RuntimeException::class);
        app(PayrollService::class)->generate(self::PERIOD);   // one sheet per month
    }

    public function test_marketing_staff_get_commission_and_leave_the_old_payroll(): void
    {
        $rep = Representative::create(['employee_code' => 'REP-9', 'name' => 'Field rep', 'commission_rate' => 5, 'salary_base' => 12000, 'active' => true]);
        $emp = Employee::create(['employee_code' => 'M-1', 'name' => 'Field rep', 'join_date' => '2025-01-01', 'category' => 'marketing',
            'representative_id' => $rep->id, 'track_attendance' => false, 'basic' => 12000]);
        Advance::create(['representative_id' => $rep->id, 'amount' => 300, 'advance_date' => '2026-09-02', 'deducted' => false]);

        $shop = $this->retailer(['representative_id' => $rep->id]);
        $order = SalesOrder::create(['number' => 'SO-1', 'order_type' => 'representative', 'retailer_id' => $shop->id, 'representative_id' => $rep->id,
            'warehouse_id' => $this->warehouse()->id, 'status' => 'completed', 'order_date' => '2026-09-10', 'subtotal' => 10000, 'discount' => 0, 'grand_total' => 10000]);
        SalesInvoice::create(['number' => 'INV-1', 'sales_order_id' => $order->id, 'retailer_id' => $shop->id, 'invoice_date' => '2026-09-12',
            'subtotal' => 10000, 'discount' => 0, 'grand_total' => 10000, 'paid_amount' => 0, 'due_amount' => 10000, 'status' => 'unpaid']);

        $run = app(PayrollService::class)->generate(self::PERIOD);
        $slip = $run->payslips()->first();
        $this->assertEquals(500, (float) $slip->commission);         // 5 % of 10,000
        $this->assertEquals(300, (float) $slip->advance_deduction);  // their old advance
        $this->assertEquals(12200, (float) $slip->net);

        app(PayrollService::class)->approve($run);
        app(PayrollService::class)->pay($run, 'cash');
        $this->assertTrue((bool) Advance::first()->deducted);

        // The old representative payroll no longer pays them.
        Livewire::test(OldPayrollCreate::class)->set('period', self::PERIOD)->call('generate')->assertHasErrors('period');

        // A new head office representative becomes an employee automatically.
        Livewire::test(\App\Livewire\Representatives\Index::class)
            ->call('create')->set('employee_code', 'REP-10')->set('name', 'New rep')->set('salary_base', '15000')
            ->call('save')->assertHasNoErrors();
        $this->assertTrue(Employee::where('representative_id', Representative::where('employee_code', 'REP-10')->value('id'))->exists());
    }

    public function test_leave_and_loan_screens(): void
    {
        $e = $this->worker();
        $cl = LeaveType::where('code', 'CL')->value('id');

        Livewire::test(Leaves::class)
            ->set('employee_id', $e->id)->set('leave_type_id', $cl)
            ->set('from_date', '2026-10-05')->set('to_date', '2026-10-06')
            ->assertSet('days', '2')
            ->call('apply')->assertHasNoErrors();
        $leave = LeaveApplication::firstOrFail();

        Livewire::test(Leaves::class)   // overlapping days are refused
            ->set('employee_id', $e->id)->set('leave_type_id', $cl)
            ->set('from_date', '2026-10-06')->set('to_date', '2026-10-06')->set('days', '1')
            ->call('apply')->assertHasErrors('from_date');

        Livewire::test(Leaves::class)->call('decide', $leave->id, 'approved');
        $this->assertSame('approved', $leave->fresh()->status);

        Livewire::test(Loans::class)
            ->set('employee_id', $e->id)->set('type', 'loan')->set('amount', '6000')->set('installment', '1000')->set('paid_from', 'cash')
            ->call('save')->assertHasNoErrors();
        $loan = EmployeeLoan::firstOrFail();
        $this->assertTrue(JournalEntry::where('source_type', $loan->getMorphClass())->where('source_id', $loan->id)->exists(), 'the loan is posted to the accounts');
    }

    public function test_final_settlement_with_gratuity(): void
    {
        $e = $this->worker(['join_date' => '2023-05-01', 'basic' => 15000, 'house_rent' => 0, 'medical' => 0, 'conveyance' => 0]);
        EmployeeLoan::create(['employee_id' => $e->id, 'type' => 'loan', 'amount' => 2000, 'installment' => 500, 'issue_date' => '2026-01-10', 'status' => 'open']);

        $s = app(SettlementService::class)->create($e, '2026-10-10', 'resigned', true, 0, 0, null);

        $this->assertEquals(10, (float) $s->salary_days);
        $this->assertEquals(5000, (float) $s->salary_amount);       // 15,000 ÷ 30 × 10
        $this->assertEquals(45000, (float) $s->gratuity);           // 3 completed years × 30 days × 500
        $this->assertEquals(2000, (float) $s->loan_deduction);
        $this->assertEquals(round(5000 + (float) $s->leave_encashment + 45000 - 2000, 2), (float) $s->net);
        $this->assertSame('resigned', $e->fresh()->status);

        app(SettlementService::class)->pay($s, 'bank');
        $this->assertSame('closed', EmployeeLoan::first()->status);
        $this->get(route('hr.settlement.pdf', $s))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Not on a later salary sheet (someone else still is).
        $this->worker(['name' => 'Still here']);
        $october = app(PayrollService::class)->generate('2026-10');
        $this->assertSame(0, $october->payslips()->where('employee_id', $e->id)->count());
        $this->assertSame(1, $october->payslips()->count());
    }

    public function test_hr_pages_open_and_dealers_are_kept_out(): void
    {
        $e = $this->worker();
        Livewire::test(EmployeesIndex::class)
            ->call('create')->set('form.name', 'Office clerk')->set('form.basic', '12000')
            ->call('save')->assertHasNoErrors();

        foreach (['hr.employees.index', 'hr.attendance', 'hr.leaves', 'hr.loans', 'hr.payroll.index', 'hr.setup'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('hr.employees.show', $e))->assertOk()->assertSee($e->name);

        $area = \App\Models\Area::firstOrCreate(['code' => 'AR1'], ['name' => 'Area 1']);
        $dealer = app(\App\Services\DealerService::class)->create(['code' => 'D', 'name' => 'D', 'phone' => '0', 'area_id' => $area->id], 0,
            ['name' => 'D', 'email' => 'd@d.test', 'password' => 'secret-123']);
        \App\Support\Tenant::flush();
        $this->actingAs(\App\Models\User::where('email', 'd@d.test')->first());
        $this->get(route('hr.employees.index'))->assertForbidden();
    }
}
