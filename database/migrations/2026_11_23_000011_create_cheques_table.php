<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cheques received from customers, including post-dated ones (PDC). A
 * cheque becomes money only when it clears: then a normal collection is
 * made for it. A bounced cheque that had cleared voids that collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('retailer_id')->constrained();
            $table->string('cheque_no', 40);
            $table->string('bank_name', 120);
            $table->string('branch', 120)->nullable();
            $table->date('received_date');
            $table->date('cheque_date');                                  // date written on it (due date for a PDC)
            $table->decimal('amount', 14, 2);
            $table->string('status', 12)->default('in_hand');             // in_hand, deposited, cleared, bounced, returned
            $table->date('deposited_date')->nullable();
            $table->date('cleared_date')->nullable();
            $table->date('bounced_date')->nullable();
            $table->decimal('bounce_charge', 14, 2)->default(0);          // bank charge passed on to the customer
            $table->foreignId('collection_id')->nullable()->constrained('collections')->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'cheque_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
