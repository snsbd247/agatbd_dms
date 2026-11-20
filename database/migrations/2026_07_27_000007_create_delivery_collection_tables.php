<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sales_order_id')->constrained();
            $table->enum('delivery_type', ['dealer', 'representative']);
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('delivery_date');
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'partial'])->default('pending');
            $table->timestamps();
        });

        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('retailer_id')->constrained();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('collection_date');
            $table->decimal('amount', 14, 2);
            $table->string('method')->default('cash');
            $table->string('reference_no')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
        Schema::dropIfExists('delivery_note_items');
        Schema::dropIfExists('delivery_notes');
    }
};
