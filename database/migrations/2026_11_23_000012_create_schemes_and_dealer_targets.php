<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trade schemes (discount or free goods, applied on sales orders) and
 * monthly dealer targets with an incentive when reached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 12);                                   // discount, free_goods
            $table->string('audience', 10)->default('all');               // all, dealers, retailers
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();     // this product …
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();    // … or any in this category
            $table->decimal('min_qty', 14, 3)->default(0);                // buy at least / per this many
            $table->decimal('discount_percent', 6, 2)->default(0);
            $table->foreignId('free_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('free_qty', 14, 3)->default(0);               // given per min_qty bought
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->foreignId('scheme_id')->nullable()->after('unit_price')->constrained()->nullOnDelete();
            $table->decimal('list_price', 14, 2)->nullable()->after('scheme_id');   // price before the scheme discount
            $table->boolean('is_free')->default(false)->after('list_price');      // free goods line
        });

        Schema::create('dealer_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);                                  // YYYY-MM
            $table->decimal('target_amount', 14, 2);
            $table->decimal('incentive_percent', 6, 2)->default(0);       // of purchases, when the target is reached
            $table->decimal('achieved_amount', 14, 2)->nullable();        // frozen when the incentive is posted
            $table->decimal('incentive_amount', 14, 2)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['dealer_id', 'period']);
        });

        $parent = DB::table('accounts')->where('code', '5200')->value('id');
        if ($parent && ! DB::table('accounts')->where('code', '5285')->orWhere('system_key', 'dealer_incentive')->exists()) {
            $now = now();
            DB::table('accounts')->insert(['code' => '5285', 'name' => 'Dealer Incentives', 'type' => 'expense', 'parent_id' => $parent,
                'is_group' => false, 'system_key' => 'dealer_incentive', 'is_cash' => false, 'is_bank' => false, 'active' => true,
                'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('system_key', 'dealer_incentive')->delete();
        Schema::dropIfExists('dealer_targets');
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheme_id');
            $table->dropColumn(['list_price', 'is_free']);
        });
        Schema::dropIfExists('schemes');
    }
};
