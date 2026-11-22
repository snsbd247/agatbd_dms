<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 of the ERP plan: damage and repair.
 *
 *  - damage_records: the damage register. Damaged stock found in a
 *    warehouse, in transport, in a customer return or rejected by QC. Once
 *    approved it sits in a damage store until it is decided.
 *  - damage_actions: what was decided for each part of it (repair,
 *    reprocess, sell at a discount, destroy), so 10 bags can be 6 repaired
 *    and 4 destroyed.
 *  - repair_jobs: in-house or outsourced repair / reprocessing, with cost.
 *  - machine_maintenances: service and breakdown log of the machines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_records', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('source', 12);                                // warehouse, transport, return, production, other
            $table->date('damage_date');
            $table->foreignId('warehouse_id')->constrained();            // where the damaged stock is now (the damage store once approved)
            $table->foreignId('found_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();         // the batch in the damage store
            $table->foreignId('source_batch_id')->nullable()->constrained('batches')->nullOnDelete();  // the batch it came from
            $table->string('batch_no')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('quantity_open', 14, 3);                     // not decided yet
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->text('reason');
            $table->nullableMorphs('reference');                         // the return or production batch it came from
            $table->string('status', 10)->default('reported');           // reported, approved, rejected, closed
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'damage_date']);
        });

        Schema::create('repair_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('damage_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();            // the damage store the goods are in
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('kind', 10)->default('repair');               // repair, reprocess
            $table->string('mode', 10)->default('in_house');             // in_house, outsourced
            $table->string('vendor')->nullable();
            $table->date('sent_date');
            $table->date('expected_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('status', 10)->default('open');               // open, completed, cancelled
            $table->decimal('repaired_quantity', 14, 3)->default(0);
            $table->decimal('scrapped_quantity', 14, 3)->default(0);
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->decimal('cost', 14, 2)->default(0);
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('return_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('output_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('damage_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_record_id')->constrained()->cascadeOnDelete();
            $table->string('action', 14);                                // repair, reprocess, discount_sale, destroy
            $table->decimal('quantity', 14, 3);
            $table->decimal('recovery_price', 14, 4)->nullable();        // discount sale: new cost per unit
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('repair_job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('output_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('written_off', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('machine_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12)->default('preventive');           // preventive, breakdown
            $table->date('maintenance_date');
            $table->text('description');
            $table->decimal('downtime_hours', 8, 2)->default(0);
            $table->string('vendor')->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('next_due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Repairs & Maintenance becomes a system account, so postings can find it.
        DB::table('accounts')->where('code', '5290')->whereNull('system_key')->update(['system_key' => 'repairs']);
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '5290')->where('system_key', 'repairs')->update(['system_key' => null]);
        foreach (['machine_maintenances', 'damage_actions', 'repair_jobs', 'damage_records'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
