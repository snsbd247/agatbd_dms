<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paying the foreign supplier of an import. The payable was booked at the
 * shipment's exchange rate; the bank pays at the day's rate, and the
 * difference is an exchange gain or loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_shipment_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('foreign_amount', 16, 2);                     // in the shipment's currency
            $table->decimal('booked_amount', 14, 2);                      // taka at the shipment rate (what the payable says)
            $table->decimal('paid_amount', 14, 2);                        // taka the bank actually paid
            $table->decimal('fx_difference', 14, 2);                      // + gain, − loss
            $table->foreignId('paid_from_account_id')->constrained('accounts');
            $table->string('reference', 100)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        $parent = DB::table('accounts')->where('code', '4000')->value('id');
        if ($parent && ! DB::table('accounts')->where('code', '4220')->orWhere('system_key', 'fx_gain_loss')->exists()) {
            $now = now();
            DB::table('accounts')->insert(['code' => '4220', 'name' => 'Foreign Exchange Gain/Loss', 'type' => 'income', 'parent_id' => $parent,
                'is_group' => false, 'system_key' => 'fx_gain_loss', 'is_cash' => false, 'is_bank' => false, 'active' => true,
                'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('system_key', 'fx_gain_loss')->delete();
        Schema::dropIfExists('import_payments');
    }
};
