<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VAT deducted at source (VDS, Mushak 6.6), both ways:
 *  - issued:   we kept back VAT when paying a supplier; it is owed to the
 *              government (VDS Payable) and paid with the month's return;
 *  - received: a customer kept back VAT from our invoice and gave us the
 *              certificate; it counts against our output VAT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vds_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('direction', 10);                              // issued, received
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();     // supplier (issued)
            $table->foreignId('retailer_id')->nullable()->constrained()->nullOnDelete();    // customer (received)
            $table->date('certificate_date');
            $table->string('certificate_no', 60)->nullable();             // the customer's certificate number (received)
            $table->string('invoice_no', 60)->nullable();                 // the 6.3 it relates to
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_amount', 14, 2)->default(0);         // value including VAT
            $table->decimal('vat_amount', 14, 2);                         // VAT deducted
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained('collections')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('bin', 20)->nullable()->after('email');
        });

        Schema::table('vat_returns', function (Blueprint $table) {
            $table->decimal('vds_paid', 14, 2)->default(0)->after('paid');   // VDS kept from suppliers, paid with the return
        });

        $parent = DB::table('accounts')->where('code', '2100')->value('id');
        if ($parent && ! DB::table('accounts')->where('code', '2135')->orWhere('system_key', 'vds_payable')->exists()) {
            $now = now();
            DB::table('accounts')->insert(['code' => '2135', 'name' => 'VDS Payable (VAT deducted at source)', 'type' => 'liability', 'parent_id' => $parent,
                'is_group' => false, 'system_key' => 'vds_payable', 'is_cash' => false, 'is_bank' => false, 'active' => true,
                'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('system_key', 'vds_payable')->delete();
        Schema::table('vat_returns', fn (Blueprint $table) => $table->dropColumn('vds_paid'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('bin'));
        Schema::dropIfExists('vds_certificates');
    }
};
