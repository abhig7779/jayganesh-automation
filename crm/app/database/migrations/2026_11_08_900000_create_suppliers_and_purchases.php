<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchases (stock in from a supplier, UX-FLOWS §3.6): suppliers, their bills, bill lines and
 * the payments we make to them. Money is integer paise. Rows are cancelled, never deleted.
 *
 * Supplier balance (what we owe) = opening balance + live bill totals − live payments.
 * A negative balance is an advance with the supplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('mobile', 20)->nullable();                    // E.164
            $table->string('gstin', 15)->nullable();
            $table->string('address', 500)->nullable();
            $table->bigInteger('opening_balance_paise')->default(0);      // we owe them at the start (minus = advance)
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'name']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('bill_no', 60)->nullable();                    // the supplier's bill number
            $table->date('bill_date');                                    // shop day (IST)
            $table->string('photo_path')->nullable();                     // private disk (storage/app/private)
            $table->unsignedBigInteger('subtotal_paise')->default(0);     // taxable value (before GST)
            $table->unsignedBigInteger('gst_paise')->default(0);          // GST input credit
            $table->unsignedBigInteger('total_paise')->default(0);        // subtotal + GST
            $table->unsignedBigInteger('paid_paise')->default(0);         // cache: live payments against this bill
            $table->string('status', 12)->default('received');            // received | cancelled
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 200)->nullable();
            $table->index(['branch_id', 'bill_date']);
            $table->index(['supplier_id', 'bill_date']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name', 200);                                  // product name at the time
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_cost_paise');                // before GST
            $table->decimal('gst_rate', 5, 2)->default(18);
            $table->unsignedBigInteger('taxable_paise');                  // qty × unit cost
            $table->unsignedBigInteger('gst_paise');
            $table->unsignedBigInteger('total_paise');
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete(); // optional: paid against one bill
            $table->date('date');
            $table->unsignedBigInteger('amount_paise');
            $table->string('mode', 10)->default('cash');                  // cash | upi | bank | cheque
            $table->string('reference', 80)->nullable();                  // UTR / cheque no.
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['supplier_id', 'date']);
            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
