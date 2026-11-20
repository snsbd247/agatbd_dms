<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sales_order_id')->constrained();
            $table->foreignId('delivery_note_id')->constrained();
            $table->foreignId('retailer_id')->constrained();
            $table->date('invoice_date');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->enum('status', ['pending', 'finalized', 'printed'])->default('pending');
            $table->timestamps();
            
            $table->index('sales_order_id');
            $table->index('delivery_note_id');
        });

        Schema::create('delivery_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('total_price', 14, 2);
            $table->timestamps();
            
            $table->index('delivery_invoice_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_invoice_items');
        Schema::dropIfExists('delivery_invoices');
    }
};
