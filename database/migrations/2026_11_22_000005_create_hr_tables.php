<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 of the ERP plan (plan.md §8): HR for every employee (office,
 * factory, marketing): departments, designations, shifts, holidays,
 * attendance (by hand, CSV or a ZKTeco export), leave, advances and loans,
 * the monthly salary sheet and the final settlement when someone leaves.
 *
 * Head office representatives become employees (linked by representative_id)
 * so their salary, commission and advances move into the HR salary sheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        // New accounts for salaries (gratuity, overtime); existing ones are left alone.
        (new \Database\Seeders\ChartOfAccountsSeeder())->run();

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->string('weekly_off', 10)->default('friday');
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code', 30)->unique();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('nid', 30)->nullable();
            $table->string('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('join_date');
            $table->date('exit_date')->nullable();
            $table->string('status', 12)->default('active');            // active, resigned, terminated
            $table->string('category', 12)->default('office');          // office, factory, marketing
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('factory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('representative_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('device_user_id', 30)->nullable()->unique();  // ZKTeco user / PIN
            $table->boolean('track_attendance')->default(true);
            $table->boolean('overtime_eligible')->default(false);
            $table->string('payment_method', 10)->default('cash');      // cash, bank, mobile
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no', 60)->nullable();
            $table->decimal('basic', 14, 2)->default(0);
            $table->decimal('house_rent', 14, 2)->default(0);
            $table->decimal('medical', 14, 2)->default(0);
            $table->decimal('conveyance', 14, 2)->default(0);
            $table->decimal('other_allowance', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['status', 'category']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->string('status', 12)->default('present');           // present, late, half_day, absent, leave, holiday, weekly_off
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->string('source', 10)->default('manual');            // manual, csv, device
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique();
            $table->unsignedSmallInteger('days_per_year')->default(0);
            $table->boolean('paid')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1);
            $table->string('reason')->nullable();
            $table->string('status', 10)->default('pending');            // pending, approved, rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('advance');             // advance (one go), loan (installments)
            $table->decimal('amount', 14, 2);
            $table->date('issue_date');
            $table->decimal('installment', 14, 2)->default(0);
            $table->decimal('recovered', 14, 2)->default(0);
            $table->string('status', 10)->default('open');              // open, closed
            $table->string('paid_from', 10)->default('cash');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('period', 7);                                // YYYY-MM
            $table->string('status', 10)->default('draft');             // draft, approved, paid
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_from', 10)->nullable();
            $table->timestamps();
            $table->index(['dealer_id', 'period']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->unsignedSmallInteger('month_days');
            $table->decimal('payable_days', 5, 1);        // days employed in the month (joiners / leavers)
            $table->decimal('working_days', 5, 1);
            $table->decimal('present_days', 5, 1)->default(0);
            $table->decimal('paid_leave_days', 5, 1)->default(0);
            $table->decimal('unpaid_leave_days', 5, 1)->default(0);
            $table->decimal('absent_days', 5, 1)->default(0);
            $table->decimal('basic', 14, 2)->default(0);
            $table->decimal('house_rent', 14, 2)->default(0);
            $table->decimal('medical', 14, 2)->default(0);
            $table->decimal('conveyance', 14, 2)->default(0);
            $table->decimal('other_allowance', 14, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('overtime_amount', 14, 2)->default(0);
            $table->decimal('commission', 14, 2)->default(0);
            $table->decimal('bonus', 14, 2)->default(0);
            $table->decimal('gross', 14, 2)->default(0);
            $table->decimal('absent_deduction', 14, 2)->default(0);
            $table->decimal('advance_deduction', 14, 2)->default(0);
            $table->decimal('loan_deduction', 14, 2)->default(0);
            $table->decimal('other_deduction', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('final_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('employee_id')->unique()->constrained();
            $table->date('exit_date');
            $table->string('reason', 12);                                // resigned, terminated
            $table->decimal('salary_days', 5, 1)->default(0);
            $table->decimal('salary_amount', 14, 2)->default(0);
            $table->decimal('leave_days', 5, 1)->default(0);
            $table->decimal('leave_encashment', 14, 2)->default(0);
            $table->decimal('service_years', 5, 2)->default(0);
            $table->decimal('gratuity', 14, 2)->default(0);
            $table->decimal('other_payable', 14, 2)->default(0);
            $table->decimal('loan_deduction', 14, 2)->default(0);
            $table->decimal('other_deduction', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->string('status', 10)->default('draft');              // draft, paid
            $table->string('paid_from', 10)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // Default leave types (Bangladesh Labour Act 2006: casual 10, sick 14, maternity 16 weeks).
        $now = now();
        DB::table('leave_types')->insert([
            ['name' => 'Casual Leave', 'code' => 'CL', 'days_per_year' => 10, 'paid' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sick Leave', 'code' => 'SL', 'days_per_year' => 14, 'paid' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Earned Leave', 'code' => 'EL', 'days_per_year' => 18, 'paid' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Maternity Leave', 'code' => 'ML', 'days_per_year' => 112, 'paid' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Leave Without Pay', 'code' => 'LWP', 'days_per_year' => 0, 'paid' => false, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('shifts')->insert([
            'name' => 'General (9 am – 6 pm)', 'start_time' => '09:00:00', 'end_time' => '18:00:00',
            'grace_minutes' => 10, 'weekly_off' => 'friday', 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('departments')->insert([
            ['name' => 'Administration', 'code' => 'ADM', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Accounts', 'code' => 'ACC', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Production', 'code' => 'PRD', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sales & Marketing', 'code' => 'MKT', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Store & Logistics', 'code' => 'STR', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Head office representatives become marketing employees.
        $marketing = DB::table('departments')->where('code', 'MKT')->value('id');
        foreach (DB::table('representatives')->whereNull('dealer_id')->get() as $rep) {
            DB::table('employees')->insert([
                'employee_code' => $rep->employee_code,
                'name' => $rep->name,
                'phone' => $rep->phone,
                'join_date' => substr((string) $rep->created_at, 0, 10) ?: $now->toDateString(),
                'status' => $rep->active ? 'active' : 'resigned',
                'category' => 'marketing',
                'department_id' => $marketing,
                'user_id' => $rep->user_id,
                'representative_id' => $rep->id,
                'track_attendance' => false,
                'basic' => $rep->salary_base,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['final_settlements', 'payslips', 'payroll_runs', 'employee_loans', 'leave_applications', 'leave_types', 'attendances', 'employees', 'holidays', 'shifts', 'designations', 'departments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
