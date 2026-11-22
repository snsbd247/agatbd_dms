<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 of the ERP plan (plan.md §8): factory and batch-wise production.
 *
 *  - machines: lines and machines of a factory.
 *  - boms + bom_items: the formula of a product (process manufacturing: kg
 *    or litres of each material per batch, normal wastage), kept in versions.
 *  - production_orders: one batch of a product: planned → in progress
 *    (materials issued from the raw-material store, batch by batch) → QC →
 *    completed (good output into the finished-goods store as a new batch with
 *    its manufacture and expiry dates, rejects to the damage store).
 *  - production_issues: exactly which material batches went into which
 *    production batch, for tracing and recalls.
 *  - stock_transfers: moving batches between warehouses (factory → depots).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new \Database\Seeders\ChartOfAccountsSeeder())->run();   // adds Work in Process and the applied-cost account

        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('kind', 10)->default('machine');           // line, machine
            $table->decimal('capacity_per_hour', 14, 3)->nullable();  // in the products' base unit
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('name')->nullable();
            $table->decimal('output_quantity', 14, 3);                // one batch makes this much (base unit)
            $table->decimal('expected_yield', 5, 2)->default(100);    // %
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'version']);
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 14, 4);                       // per batch, in the material's base unit
            $table->decimal('wastage_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('factory_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('bom_id')->constrained();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('planned_quantity', 14, 3);
            $table->string('batch_no');
            $table->date('mfg_date');
            $table->date('expiry_date')->nullable();
            $table->foreignId('raw_warehouse_id')->constrained('warehouses');
            $table->foreignId('output_warehouse_id')->constrained('warehouses');
            $table->foreignId('damage_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('status', 12)->default('planned');          // planned, in_progress, qc, completed, cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->decimal('good_quantity', 14, 3)->default(0);
            $table->decimal('rejected_quantity', 14, 3)->default(0);
            $table->decimal('standard_cost', 14, 2)->default(0);        // expected material cost when planned
            $table->decimal('material_cost', 14, 2)->default(0);
            $table->decimal('labour_cost', 14, 2)->default(0);
            $table->decimal('overhead_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->foreignId('output_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'mfg_date']);
            $table->index('batch_no');
        });

        Schema::create('production_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('issue_no')->default(1);       // each issuing action
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->constrained('batches');
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 4);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('batch_id');
        });

        Schema::create('production_qc_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->string('result', 10);                               // pass, fail, rework
            $table->text('parameters')->nullable();                     // e.g. moisture %, N-P-K, pH
            $table->text('notes')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->date('transfer_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->constrained('batches');      // where it came from
            $table->foreignId('to_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['stock_transfer_items', 'stock_transfers', 'production_qc_checks', 'production_issues', 'production_orders', 'bom_items', 'boms', 'machines'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
