<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the ERP plan (plan.md §8): double-entry accounts.
 *
 *  - accounts: one chart for everyone (dealer_id null). A dealer may add its
 *    own accounts (e.g. its bank) which only it sees.
 *  - journal_entries + journal_lines: vouchers. Each business keeps its own
 *    books (dealer_id null = head office). Automatic postings from sales,
 *    purchases, collections, payroll … carry their source document, and
 *    (source_type, source_id, source_event) is unique, so posting twice is
 *    impossible.
 *  - Lines may name a party (a customer, a supplier company, the head office
 *    for a dealer's payable, a representative) for party ledgers and aging.
 *  - fiscal_year_closings: a closed year is locked for manual vouchers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 10);                       // asset, liability, equity, income, expense
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_group')->default(false);
            $table->string('system_key', 30)->nullable()->unique();
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_bank')->default(false);
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('type');
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('dealer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);                        // journal, payment, receipt, contra, opening, auto, closing
            $table->date('entry_date');
            $table->text('narration')->nullable();
            $table->string('status', 10)->default('posted');   // draft, posted, cancelled
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event', 40)->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'source_event']);
            $table->index(['dealer_id', 'entry_date']);
            $table->index(['status', 'entry_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('debit', 16, 2)->default(0);
            $table->decimal('credit', 16, 2)->default(0);
            $table->string('party_type', 20)->nullable();      // retailer, company, head_office, representative
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('narration')->nullable();
            $table->date('cleared_at')->nullable();            // bank reconciliation
            $table->timestamps();
            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['party_type', 'party_id']);
        });

        Schema::create('fiscal_year_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        (new \Database\Seeders\ChartOfAccountsSeeder())->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_closings');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
