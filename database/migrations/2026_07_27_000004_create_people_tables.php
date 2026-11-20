<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('salary_base', 14, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('target_amount', 14, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('retailers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('representative_id')->nullable()->constrained('representatives')->nullOnDelete();
            $table->foreignId('area_id')->constrained();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('shop_name')->nullable();
            $table->string('phone');
            $table->string('address')->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('current_due', 14, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('retailer_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('balance_after', 14, 2);
            $table->date('entry_date');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailer_ledger');
        Schema::dropIfExists('retailers');
        Schema::dropIfExists('representatives');
    }
};
