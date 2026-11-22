<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP phase 6: VAT (Mushak 6.3) and attendance machines that push their
 * punches to the server (ZKTeco ADMS / "iclock" protocol).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(0)->after('sale_price');     // % included in the sale price; 0 = exempt
        });

        Schema::table('retailers', function (Blueprint $table) {
            $table->string('bin', 20)->nullable()->after('address');              // buyer's VAT registration number
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->decimal('vat_amount', 14, 2)->default(0)->after('grand_total');
        });

        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(0)->after('line_total');
            $table->decimal('vat_amount', 14, 2)->default(0)->after('vat_rate');
        });

        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no', 40)->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->unsignedInteger('punch_count')->default(0);
            $table->timestamps();
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_user_id', 30);
            $table->dateTime('punched_at');
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('processed')->default(false);
            $table->timestamps();
            $table->unique(['device_user_id', 'punched_at']);
            $table->index(['processed', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('attendance_devices');
        Schema::table('sales_invoice_items', fn (Blueprint $t) => $t->dropColumn(['vat_rate', 'vat_amount']));
        Schema::table('sales_invoices', fn (Blueprint $t) => $t->dropColumn('vat_amount'));
        Schema::table('retailers', fn (Blueprint $t) => $t->dropColumn('bin'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('vat_rate'));
    }
};
