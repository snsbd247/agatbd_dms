<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed asset register and monthly depreciation. The Depreciation expense
 * and Accumulated Depreciation accounts get system keys so the postings can
 * find them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category', 30);                               // building, machinery, vehicle, furniture, computer, other
            $table->foreignId('account_id')->constrained('accounts');     // the asset account it sits in
            $table->string('location')->nullable();
            $table->date('purchase_date');
            $table->decimal('cost', 14, 2);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->string('method', 20)->default('straight_line');       // straight_line, reducing_balance
            $table->unsignedSmallInteger('useful_life_months')->default(60);
            $table->decimal('rate_percent', 6, 2)->default(0);            // yearly rate for reducing balance
            $table->decimal('accumulated_depreciation', 14, 2)->default(0);
            $table->string('funded_by', 20)->default('opening');          // opening, bank, cash, supplier
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();   // supplier, when bought on credit
            $table->string('status', 12)->default('active');              // active, disposed
            $table->date('disposed_date')->nullable();
            $table->decimal('disposal_amount', 14, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);                                  // YYYY-MM
            $table->decimal('amount', 14, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['fixed_asset_id', 'period']);
        });

        DB::table('accounts')->where('code', '5310')->whereNull('system_key')->update(['system_key' => 'depreciation']);
        DB::table('accounts')->where('code', '1290')->whereNull('system_key')->update(['system_key' => 'accum_depreciation']);
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('system_key', ['depreciation', 'accum_depreciation'])->update(['system_key' => null]);
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('fixed_assets');
    }
};
