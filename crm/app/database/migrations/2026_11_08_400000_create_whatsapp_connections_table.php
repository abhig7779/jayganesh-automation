<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * whatsapp_connections: the number connected from the CRM's "Connect WhatsApp" page (Embedded
     * Signup, coexistence with the WhatsApp Business app). The token is encrypted by the model cast
     * and never leaves the server. .env values (WHATSAPP_TOKEN, WHATSAPP_PHONE_NUMBER_ID) still win
     * when they are filled.
     *
     * whatsapp_ai_chats: one row per customer number for the AI auto-reply: whether the chat came
     * from a Click-to-WhatsApp ad, and when a person took over (AI stays quiet until paused_until).
     */
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->string('waba_id', 32);
            $table->string('phone_number_id', 32)->nullable();
            $table->string('business_id', 32)->nullable();
            $table->string('display_phone', 32)->nullable();
            $table->string('verified_name', 120)->nullable();
            $table->text('token');                                   // encrypted (model cast)
            $table->boolean('is_on_biz_app')->nullable();            // true = coexistence
            $table->string('platform_type', 32)->nullable();
            $table->string('status', 16)->default('connected');      // connected | failed | disconnected
            $table->string('contacts_sync_request_id', 64)->nullable();
            $table->string('history_sync_request_id', 64)->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->json('signup_event')->nullable();                // WA_EMBEDDED_SIGNUP session info (no token)
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'id']);
        });

        Schema::create('whatsapp_ai_chats', function (Blueprint $table) {
            $table->id();
            $table->string('contact', 20)->unique();                 // customer number, E.164
            $table->timestamp('ad_referral_at')->nullable();         // last message that came from an ad
            $table->json('ad_referral')->nullable();                 // headline, body, source_url, source_id
            $table->timestamp('paused_until')->nullable();           // a person is handling this chat
            $table->string('paused_reason', 32)->nullable();         // owner_replied | handoff | manual
            $table->timestamp('last_ai_reply_at')->nullable();
            $table->unsignedSmallInteger('ai_replies_today')->default(0);
            $table->date('ai_replies_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_ai_chats');
        Schema::dropIfExists('whatsapp_connections');
    }
};
