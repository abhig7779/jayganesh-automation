<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per WhatsApp message, in or out (ARCHITECTURE.md §2, §4).
     * `to` is always the customer's number in E.164, also for inbound messages (it is the
     * other side of the chat), so one index finds a customer's whole conversation.
     */
    public function up(): void
    {
        Schema::create('message_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 20)->index();
            $table->string('direction', 3)->default('out');      // out | in
            $table->string('channel', 12)->default('manual');    // cloud_api | manual
            $table->string('template', 80)->nullable()->index();
            $table->string('category', 12)->default('utility');  // utility | marketing | service
            $table->json('body')->nullable();                    // params, text, document info
            $table->string('document_name', 120)->nullable();
            $table->string('wa_message_id', 128)->nullable()->index();
            $table->string('status', 16)->default('queued')->index(); // queued|sent|delivered|read|failed|manual_opened
            $table->string('error_code', 32)->nullable();
            $table->string('error_text', 500)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->nullableMorphs('related');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_log');
    }
};
