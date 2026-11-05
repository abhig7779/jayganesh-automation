<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quotations, their lines, and the gap-free number counter (UX-FLOWS §3.2). Money is integer paise. */
return new class extends Migration
{
    public function up(): void
    {
        // One row per branch + financial year; locked with SELECT … FOR UPDATE while numbering.
        Schema::create('quotation_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('fy', 4);                    // "2627" = Apr 2026 – Mar 2027
            $table->unsignedInteger('last_no')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'fy']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30);               // JGQ-2627-0001
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_car_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open'); // open | visit_booked | won | lost | expired
            $table->date('valid_until');
            $table->date('visit_on')->nullable();
            $table->unsignedBigInteger('taxable_paise')->default(0);
            $table->unsignedBigInteger('gst_paise')->default(0);
            $table->unsignedBigInteger('total_paise')->default(0); // GST-inclusive grand total
            $table->unsignedBigInteger('savings_paise')->default(0); // Σ (MRP − our price) × qty, where MRP is higher
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedBigInteger('revised_from_id')->nullable()->index();
            $table->unsignedBigInteger('revised_to_id')->nullable()->index();
            $table->unsignedBigInteger('won_invoice_id')->nullable()->index(); // no FK: billing owns invoices
            $table->timestamp('won_at')->nullable();
            $table->string('lost_reason', 30)->nullable();
            $table->string('lost_note', 300)->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('sent_at')->nullable();   // first time it went out on WhatsApp
            $table->string('source', 30)->nullable();   // walk_in, phone_call, whatsapp_ad, lead …
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->index(['status', 'valid_until']);
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('created_at');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('hsn', 10)->nullable();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_price_paise');      // GST-inclusive
            $table->unsignedBigInteger('mrp_paise')->nullable();     // MRP snapshot (GST-inclusive), for "you save"
            $table->decimal('gst_rate', 5, 2)->default(18);
            $table->unsignedBigInteger('taxable_paise');
            $table->unsignedBigInteger('gst_paise');
            $table->unsignedBigInteger('line_total_paise');      // qty × unit, GST-inclusive
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('quotation_counters');
    }
};
