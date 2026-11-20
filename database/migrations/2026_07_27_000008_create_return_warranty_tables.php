<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('retailer_id')->constrained();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('request_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'received'])->default('pending');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 14, 3);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('return_receives', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('return_request_id')->constrained();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->date('receive_date');
            $table->timestamps();
        });

        Schema::create('return_receive_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_receive_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->enum('disposition', ['restock', 'damage', 'company_return'])->default('restock');
            $table->timestamps();
        });

        Schema::create('company_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->date('return_date');
            $table->enum('status', ['draft', 'sent', 'completed'])->default('draft');
            $table->timestamps();
        });

        Schema::create('company_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });

        Schema::create('warranty_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('duration_months');
            $table->text('terms')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('retailer_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('sales_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('claim_date');
            $table->text('issue_description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'replaced', 'repaired'])->default('pending');
            $table->timestamps();
        });

        Schema::create('warranty_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_tracking');
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranty_policies');
        Schema::dropIfExists('company_return_items');
        Schema::dropIfExists('company_returns');
        Schema::dropIfExists('return_receive_items');
        Schema::dropIfExists('return_receives');
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
    }
};
