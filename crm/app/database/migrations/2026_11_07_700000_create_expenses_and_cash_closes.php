<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Finance module: expense book and the daily cash close (CRM-PLAN.md §3 item 8, UX-FLOWS.md §3.8). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');                                   // shop day (IST), not a timestamp
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->string('mode', 10)->default('cash');            // cash | upi | bank
            $table->string('paid_to', 120)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('receipt_path')->nullable();             // private disk (storage/app/private)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();          // rows are cancelled, never deleted
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['branch_id', 'date']);
            $table->index(['branch_id', 'mode', 'date']);
        });

        Schema::create('cash_closes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->bigInteger('opening_float_paise')->default(0);
            $table->bigInteger('cash_in_paise')->default(0);        // cash payments that day
            $table->bigInteger('cash_out_paise')->default(0);       // cash expenses that day
            $table->bigInteger('expected_cash_paise');
            $table->bigInteger('counted_cash_paise');
            $table->bigInteger('difference_paise');                 // counted − expected (minus = short)
            $table->string('note', 500)->nullable();                // required when difference ≠ 0
            $table->json('summary')->nullable();                    // snapshot of the day for the WhatsApp text
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'date']);
        });

        $now = now();
        $names = ['Rent', 'Electricity', 'Salaries', 'Tea & snacks', 'Transport', 'Supplies', 'Marketing', 'Repairs', 'Other'];
        DB::table('expense_categories')->insert(array_map(fn ($name, $i) => [
            'name' => $name, 'sort' => ($i + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $names, array_keys($names)));
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closes');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
