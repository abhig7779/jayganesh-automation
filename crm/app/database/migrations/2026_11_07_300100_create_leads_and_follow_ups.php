<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Leads (enquiries, "asked price") and the follow-up engine shared by leads and quotations. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('mobile', 20)->index();      // E.164
            $table->string('name', 120)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('enquiry'); // enquiry | price_enquiry ("Asked price")
            $table->text('interest')->nullable();       // what they asked for
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('price_told_paise')->nullable();
            $table->string('source', 30)->default('walk_in_enquiry');
            $table->string('status', 20)->default('new'); // new | contacted | quote_sent | visit_booked | won | lost
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('quotation_id')->nullable()->index();
            $table->date('visit_on')->nullable();
            $table->string('lost_reason', 30)->nullable();
            $table->string('lost_note', 300)->nullable();
            $table->unsignedBigInteger('won_invoice_id')->nullable()->index();
            $table->timestamp('won_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('subject');                  // App\Models\Quotation | App\Models\Lead
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on');
            $table->unsignedSmallInteger('step')->default(1); // schedule day: 0 (lead), 1, 3, 6, 10
            $table->string('kind', 20)->default('scheduled'); // scheduled | call_later | visit | retry
            $table->string('status', 10)->default('pending'); // pending | done | skipped
            $table->string('outcome', 30)->nullable();
            $table->string('outcome_note', 500)->nullable();
            $table->date('visit_on')->nullable();
            $table->string('channel', 10)->nullable();  // whatsapp | call
            $table->timestamp('contacted_at')->nullable(); // WhatsApp / Call button pressed
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->json('undo')->nullable();           // what logging changed, for the 10-second Undo
            $table->timestamps();

            $table->index(['status', 'due_on']);
            $table->index(['assigned_to', 'status', 'due_on']);
        });

        // Database notifications ("wants a better price" → Owner/Admin). Another module may
        // create this standard Laravel table first, so only create it when missing.
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('leads');
    }
};
