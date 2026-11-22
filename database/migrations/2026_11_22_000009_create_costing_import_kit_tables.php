<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERP phase 7b–7d: price tiers and cost sheets (price declaration,
 * Mushak 4.3), import shipments with landed cost, and kits / repacking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('dealer_price', 14, 2)->default(0)->after('sale_price');   // 0 = use the sale (retailer) price
            $table->decimal('mrp', 14, 2)->default(0)->after('dealer_price');
            $table->string('hs_code', 20)->nullable()->after('barcode');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->decimal('dealer_price', 14, 2)->nullable()->after('sale_price');
            $table->decimal('mrp', 14, 2)->nullable()->after('dealer_price');
            $table->foreignId('cost_sheet_id')->nullable()->after('mrp');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('country', 60)->nullable()->after('address');
            $table->string('currency', 3)->default('BDT')->after('country');
        });

        Schema::table('boms', function (Blueprint $table) {
            $table->string('kind', 10)->default('production')->after('product_id');   // production, kit, repack
        });

        Schema::create('cost_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('basis', 10)->default('percent');                 // percent (of base cost) or amount (per unit)
            $table->decimal('default_value', 14, 4)->default(0);
            $table->string('applies_to', 15)->default('all');                // all, trading, manufacturing
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('cost_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('product_id')->constrained();
            $table->unsignedSmallInteger('version');
            $table->string('basis', 10);                                     // purchase, import, formula, kit
            $table->foreignId('bom_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_cost', 14, 4)->default(0);                 // per unit: materials or buying cost
            $table->decimal('overhead_cost', 14, 4)->default(0);
            $table->decimal('total_cost', 14, 4)->default(0);
            $table->decimal('company_margin', 6, 2)->default(0);             // % on cost → dealer price
            $table->decimal('dealer_margin', 6, 2)->default(0);              // % on dealer price → retailer price
            $table->decimal('retailer_margin', 6, 2)->default(0);            // % on retailer price → MRP
            $table->decimal('dealer_price', 14, 2)->default(0);
            $table->decimal('retailer_price', 14, 2)->default(0);
            $table->decimal('mrp', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->date('effective_from');
            $table->date('first_supply_date')->nullable();
            $table->string('status', 12)->default('draft');                  // draft, approved, applied, superseded, rejected
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'version']);
            $table->index(['status', 'effective_from']);
        });

        Schema::create('cost_sheet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_sheet_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                                      // material, overhead
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_head_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 4)->nullable();                  // per unit of output, with wastage
            $table->decimal('wastage_percent', 5, 2)->default(0);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('basis', 10)->nullable();                         // overhead: percent or amount
            $table->decimal('rate', 14, 4)->nullable();
            $table->decimal('amount', 14, 4)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('import_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('company_id')->constrained();                  // the foreign supplier
            $table->foreignId('warehouse_id')->constrained();                // where the goods go
            $table->string('proforma_no')->nullable();
            $table->date('proforma_date')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->string('lc_no')->nullable();
            $table->date('lc_date')->nullable();
            $table->foreignId('lc_bank_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('lc_margin', 14, 2)->default(0);
            $table->string('bl_no')->nullable();
            $table->date('shipment_date')->nullable();
            $table->date('eta')->nullable();
            $table->date('arrival_date')->nullable();
            $table->string('port', 60)->nullable();
            $table->string('allocation_basis', 10)->default('value');        // value, quantity, weight
            $table->string('status', 12)->default('draft');                  // draft, lc_opened, shipped, arrived, received, cancelled
            $table->date('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price_fc', 14, 4);                         // in the shipment's currency
            $table->decimal('weight_kg', 14, 3)->default(0);
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('landed_unit_cost', 14, 4)->default(0);          // set on receiving
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('import_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_shipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 15);                                      // cd, rd, sd, vat, at, ait, cnf, port, transport, insurance, bank, other
            $table->decimal('amount', 14, 2);
            $table->date('cost_date');
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assembly_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('kind', 10);                                      // kit, repack
            $table->foreignId('bom_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->decimal('quantity', 14, 3);
            $table->date('assembly_date');
            $table->string('batch_no');
            $table->date('expiry_date')->nullable();
            $table->decimal('material_cost', 14, 2)->default(0);
            $table->decimal('labour_cost', 14, 2)->default(0);
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->foreignId('output_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assembly_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assembly_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->timestamps();
        });

        // Usual cost heads; percentages are of the base (buying or material) cost.
        $now = now();
        DB::table('cost_heads')->insert([
            ['name' => 'Direct labour', 'code' => 'LAB', 'basis' => 'percent', 'default_value' => 5, 'applies_to' => 'manufacturing', 'sort' => 1, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Factory overhead (power, fuel, depreciation)', 'code' => 'FOH', 'basis' => 'percent', 'default_value' => 4, 'applies_to' => 'manufacturing', 'sort' => 2, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Carriage & transport', 'code' => 'TRN', 'basis' => 'percent', 'default_value' => 2, 'applies_to' => 'all', 'sort' => 3, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Administrative overhead', 'code' => 'ADM', 'basis' => 'percent', 'default_value' => 3, 'applies_to' => 'all', 'sort' => 4, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Selling & marketing', 'code' => 'SEL', 'basis' => 'percent', 'default_value' => 4, 'applies_to' => 'all', 'sort' => 5, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Finance cost (bank interest)', 'code' => 'FIN', 'basis' => 'percent', 'default_value' => 1, 'applies_to' => 'all', 'sort' => 6, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Accounts the import needs.
        $parent = DB::table('accounts')->where('code', '1100')->value('id');
        if ($parent) {
            foreach ([
                ['1175', 'Advance Income Tax (AIT)', 'advance_tax'],
                ['1185', 'Goods in Transit (Import)', 'goods_in_transit'],
                ['1186', 'LC Margin', 'lc_margin'],
            ] as [$code, $name, $key]) {
                if (! DB::table('accounts')->where('code', $code)->orWhere('system_key', $key)->exists()) {
                    DB::table('accounts')->insert(['code' => $code, 'name' => $name, 'type' => 'asset', 'parent_id' => $parent,
                        'is_group' => false, 'system_key' => $key, 'is_cash' => false, 'is_bank' => false, 'active' => true,
                        'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('system_key', ['advance_tax', 'goods_in_transit', 'lc_margin'])->delete();
        Schema::dropIfExists('assembly_order_items');
        Schema::dropIfExists('assembly_orders');
        Schema::dropIfExists('import_costs');
        Schema::dropIfExists('import_items');
        Schema::dropIfExists('import_shipments');
        Schema::dropIfExists('cost_sheet_items');
        Schema::dropIfExists('cost_sheets');
        Schema::dropIfExists('cost_heads');
        Schema::table('boms', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn(['country', 'currency']));
        Schema::table('product_prices', fn (Blueprint $t) => $t->dropColumn(['dealer_price', 'mrp', 'cost_sheet_id']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['dealer_price', 'mrp', 'hs_code']));
    }
};
