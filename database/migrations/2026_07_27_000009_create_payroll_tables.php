<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('representative_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representative_id')->constrained()->cascadeOnDelete();
            $table->string('period');
            $table->decimal('target_amount', 14, 2)->default(0);
            $table->decimal('achieved_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['representative_id', 'period']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period');
            $table->decimal('delivered_amount', 14, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('return_adjustment', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representative_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('advance_date');
            $table->string('reason')->nullable();
            $table->boolean('deducted')->default(false);
            $table->timestamps();
        });

        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representative_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('reason')->nullable();
            $table->date('bonus_date');
            $table->timestamps();
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('representative_id')->constrained();
            $table->string('period');
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('bonus_amount', 14, 2)->default(0);
            $table->decimal('advance_deduction', 14, 2)->default(0);
            $table->decimal('other_deduction', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2)->default(0);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('bonuses');
        Schema::dropIfExists('advances');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('representative_targets');
    }
};
