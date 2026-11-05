<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing (crm/CONTRACTS.md, ARCHITECTURE.md §4). Money is integer paise everywhere.
 * Bills are never deleted: a cancelled bill keeps its number and gets status = cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per branch + financial year + document type. The bill transaction locks the
        // row (SELECT … FOR UPDATE) to take the next number, so numbers have no gaps.
        Schema::create('invoice_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('financial_year', 4);            // "2627" = Apr 2026 – Mar 2027
            $table->string('type', 20)->default('invoice'); // invoice | credit_note
            $table->string('prefix', 12);
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['branch_id', 'financial_year', 'type']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 30);
            $table->string('financial_year', 4);
            $table->string('kind', 20)->default('tax_invoice'); // tax_invoice | bill_of_supply
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete(); // null = walk-in
            $table->foreignId('customer_car_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('quotation_id')->nullable()->index(); // no FK: quotations ship on another branch
            $table->string('customer_name', 120)->nullable();  // snapshot at billing time
            $table->string('customer_mobile', 20)->nullable();
            $table->string('car_label', 120)->nullable();      // "Hyundai Creta · MH12KT4521"
            $table->string('seller_gstin', 15)->nullable();
            $table->string('customer_gstin', 15)->nullable();
            $table->string('place_of_supply', 2);              // state code, "27" = Maharashtra
            $table->unsignedBigInteger('gross_paise')->default(0);     // list value before discounts
            $table->unsignedBigInteger('subtotal_paise')->default(0);  // taxable value
            $table->unsignedBigInteger('cgst_paise')->default(0);
            $table->unsignedBigInteger('sgst_paise')->default(0);
            $table->unsignedBigInteger('igst_paise')->default(0);
            $table->unsignedBigInteger('discount_paise')->default(0);
            $table->string('discount_reason', 60)->nullable();
            $table->unsignedBigInteger('mrp_savings_paise')->default(0); // Σ (MRP − our price) × qty
            $table->integer('round_off_paise')->default(0);            // −49 … +50
            $table->unsignedBigInteger('total_paise')->default(0);
            $table->unsignedBigInteger('paid_paise')->default(0);
            $table->unsignedBigInteger('due_paise')->default(0);
            $table->unsignedBigInteger('credited_paise')->default(0);  // partial returns (credit notes)
            $table->string('status', 12)->default('paid');             // paid | partial | due | cancelled
            $table->boolean('send_whatsapp')->default(true);
            $table->string('draft_key', 64)->nullable();               // idempotency: one bill per draft
            $table->string('cancel_reason', 300)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->unique(['branch_id', 'draft_key']);
            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'status']);
            $table->index(['created_by', 'created_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // null = custom line
            $table->string('name', 160);
            $table->string('hsn', 10)->nullable();
            $table->string('unit', 10)->default('pcs');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('returned_qty')->default(0);
            $table->unsignedBigInteger('unit_price_paise');            // as charged (see price_includes_gst)
            $table->boolean('price_includes_gst')->default(true);
            $table->unsignedBigInteger('list_price_paise')->nullable(); // product price at billing time
            $table->unsignedBigInteger('mrp_paise')->nullable();        // MRP snapshot for "You save" (GST is never on MRP)
            $table->unsignedBigInteger('discount_paise')->default(0);   // line discount + share of bill discount
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('taxable_paise');
            $table->unsignedBigInteger('cgst_paise')->default(0);
            $table->unsignedBigInteger('sgst_paise')->default(0);
            $table->unsignedBigInteger('igst_paise')->default(0);
            $table->unsignedBigInteger('line_total_paise');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete(); // null = spread over several bills
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10)->default('receipt');  // receipt | refund (refunds are negative)
            $table->string('mode', 10);                       // cash | upi | card | bank
            $table->bigInteger('amount_paise');
            $table->string('reference', 80)->nullable();      // UPI ref / card slip no.
            $table->string('note', 200)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'mode']);
            $table->index(['received_by', 'created_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_paise');
            $table->timestamps();
            $table->index('invoice_id');
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 30);
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 300);
            $table->unsignedBigInteger('subtotal_paise')->default(0);
            $table->unsignedBigInteger('cgst_paise')->default(0);
            $table->unsignedBigInteger('sgst_paise')->default(0);
            $table->unsignedBigInteger('igst_paise')->default(0);
            $table->unsignedBigInteger('total_paise')->default(0);
            $table->unsignedBigInteger('refund_paise')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'number']);
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('taxable_paise');
            $table->unsignedBigInteger('cgst_paise')->default(0);
            $table->unsignedBigInteger('sgst_paise')->default(0);
            $table->unsignedBigInteger('igst_paise')->default(0);
            $table->unsignedBigInteger('line_total_paise');
            $table->timestamps();
        });

        // Half-made bills (UX-FLOWS §0.4): one open draft per staff member, synced from the
        // browser every few seconds so it survives a closed tab or a different device.
        Schema::create('bill_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('draft_key', 64);
            $table->json('payload');
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedBigInteger('total_paise')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_drafts');
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_series');
    }
};
