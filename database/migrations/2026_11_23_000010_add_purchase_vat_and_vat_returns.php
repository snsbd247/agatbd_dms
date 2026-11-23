<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Input VAT on local purchases (the supplier's Mushak 6.3 number and the VAT
 * on it) and the monthly VAT settlement: output VAT set off against input
 * VAT, the rest paid to the government.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('supplier_invoice_no', 60)->nullable()->after('receipt_date');   // supplier's Mushak 6.3 number
            $table->decimal('vat_amount', 14, 2)->default(0)->after('total_amount');          // input VAT on that invoice
        });

        Schema::create('vat_returns', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();                    // YYYY-MM
            $table->decimal('output_vat', 14, 2)->default(0);         // Output VAT account balance at month end
            $table->decimal('input_vat', 14, 2)->default(0);          // Input VAT (incl. import AT) at month end
            $table->decimal('set_off', 14, 2)->default(0);            // input used against output
            $table->decimal('paid', 14, 2)->default(0);               // paid to the government
            $table->decimal('carried_forward', 14, 2)->default(0);    // input VAT left for next month
            $table->string('paid_from', 20)->nullable();              // bank / cash
            $table->string('reference', 100)->nullable();             // treasury challan / e-payment no.
            $table->date('paid_on')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_returns');
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn(['supplier_invoice_no', 'vat_amount']);
        });
    }
};
