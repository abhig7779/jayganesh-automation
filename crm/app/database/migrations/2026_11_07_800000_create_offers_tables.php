<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offers / campaigns, saved customer segments and the Meta Custom Audience export log
 * (CRM-PLAN.md §3 items 10–11, UX-FLOWS.md §3.9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->json('filters');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('message');
            $table->json('filters');                                 // segment snapshot at creation
            $table->foreignId('segment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('send_method', 20)->nullable();         // broadcast | assisted | automatic
            $table->string('template_name', 80)->nullable();       // approved MARKETING template (automatic only)
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 20)->default('draft');        // draft | sending | sent | exported
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('opted_out_excluded')->default(0);
            $table->unsignedBigInteger('estimated_cost_paise')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('offer_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('mobile', 20);                           // E.164 at the time of sending
            $table->string('name', 120);
            $table->string('status', 20)->default('pending');      // pending | queued | sent | skipped | failed | exported
            $table->unsignedBigInteger('message_log_id')->nullable(); // WhatsApp module's message log row
            $table->string('error', 255)->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['offer_id', 'customer_id']);
            $table->index(['offer_id', 'status']);
        });

        // Privacy audit: every customer-list download for Meta ads (CRM-PLAN.md §6).
        Schema::create('audience_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('segment_name', 120);
            $table->json('filters');
            $table->string('format', 10);                           // plain | sha256
            $table->json('fields');                                 // ["phone","fn","ln",…]
            $table->unsignedInteger('row_count');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_exports');
        Schema::dropIfExists('offer_recipients');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('segments');
    }
};
