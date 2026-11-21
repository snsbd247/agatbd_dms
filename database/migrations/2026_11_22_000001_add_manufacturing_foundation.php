<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 of the Agro Tech ERP plan (plan.md §8): the master data that
 * manufacturing, accounts and the dealer panel build on.
 *
 *  - units.kind and unit_conversions: 1 ton = 1000 kg, 1 L = 1000 ml …
 *  - products.type (raw material, packaging, semi-finished, finished good,
 *    trading) and shelf_life_days (default expiry of a produced batch)
 *  - product_units: a product's own packs, e.g. 1 bag of urea = 50 kg
 *  - factories, and warehouses linked to a factory with a purpose
 *    (raw-material store, production floor, finished goods, damage)
 *
 * Kinds, types and purposes are plain strings checked by the app (see the
 * models' constants), so new values never need a schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('kind', 10)->default('count')->after('short_code');
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('to_unit_id')->constrained('units')->cascadeOnDelete();
            $table->decimal('factor', 18, 6);   // 1 from_unit = factor × to_unit
            $table->timestamps();
            $table->unique(['from_unit_id', 'to_unit_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('type', 20)->default('trading')->after('unit_id');
            $table->unsignedInteger('shelf_life_days')->nullable()->after('reorder_level');
            $table->index('type');
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->decimal('factor', 18, 6);   // 1 of this unit = factor × the product's base unit
            $table->string('barcode')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'unit_id']);
        });

        Schema::create('factories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('factory_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->string('purpose', 20)->default('general')->after('factory_id');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factory_id');
            $table->dropColumn('purpose');
        });
        Schema::dropIfExists('factories');
        Schema::dropIfExists('product_units');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'shelf_life_days']);
        });
        Schema::dropIfExists('unit_conversions');
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
