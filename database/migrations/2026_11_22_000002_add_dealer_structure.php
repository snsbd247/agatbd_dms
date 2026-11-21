<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the Agro Tech ERP plan (plan.md §8): Agro Tech's head office
 * sells directly and to dealers; each dealer runs its own panel.
 *
 *  - dealers: the dealer businesses.
 *  - dealer_id on users, retailers, warehouses and representatives says whose
 *    the row is: null = head office, otherwise that dealer. Every other
 *    business record (orders, invoices, stock …) belongs to whoever owns its
 *    retailer or warehouse, so it needs no column of its own.
 *  - retailers.account_dealer_id marks the head office's customer record of a
 *    dealer: orders to Agro Tech, the dealer's credit limit and its due run
 *    through it with the ordinary sales, delivery and collection screens.
 *  - dealer_representative: head office marketing staff placed with dealers.
 *  - The role that could do everything was called "Dealer"; it becomes
 *    "Admin", and "Dealer" now means a dealer's own login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('owner_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('dealer_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('retailers', function (Blueprint $table) {
            $table->foreignId('dealer_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('account_dealer_id')->nullable()->unique()->after('dealer_id')->constrained('dealers')->nullOnDelete();
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('dealer_id')->nullable()->after('factory_id')->constrained()->nullOnDelete();
        });

        Schema::table('representatives', function (Blueprint $table) {
            $table->foreignId('dealer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::create('dealer_representative', function (Blueprint $table) {
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('representative_id')->constrained()->cascadeOnDelete();
            $table->primary(['dealer_id', 'representative_id']);
        });

        // The all-powerful role is renamed; its users and permissions stay as they are.
        DB::table('roles')->where('name', 'Dealer')->where('guard_name', 'web')->update(['name' => 'Admin']);
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Dealer')->where('guard_name', 'web')->delete();
        DB::table('roles')->where('name', 'Admin')->where('guard_name', 'web')->update(['name' => 'Dealer']);

        Schema::dropIfExists('dealer_representative');
        Schema::table('representatives', fn (Blueprint $t) => $t->dropConstrainedForeignId('dealer_id'));
        Schema::table('warehouses', fn (Blueprint $t) => $t->dropConstrainedForeignId('dealer_id'));
        Schema::table('retailers', function (Blueprint $t) {
            $t->dropUnique(['account_dealer_id']);   // SQLite rebuilds the table and trips over the index otherwise
            $t->dropConstrainedForeignId('account_dealer_id');
            $t->dropConstrainedForeignId('dealer_id');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('dealer_id'));
        Schema::dropIfExists('dealers');
    }
};
