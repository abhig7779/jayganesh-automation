<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders & reviews module (CRM-PLAN §3 item 14). Rules say which bill lines start a
 * service check-up reminder and after how many days; service_reminders is the work list
 * (check-ups and Google review requests). The default rules are inserted here so a fresh
 * production install has them without running the demo seeders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);                                  // "Ceramic coating check-up"
            $table->string('match_type', 12)->default('name');           // product | category | name
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('keywords', 200)->nullable();                 // name match, comma separated
            $table->boolean('services_only')->default(false);            // skip DIY products for "ceramic"
            $table->json('offsets_days');                                // [180, 365]
            $table->text('message');                                     // {name} {car} {service} {date} {shop}
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('service_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10)->default('service');              // service | review
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_car_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reminder_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('offset_days')->nullable();
            $table->string('service_name', 160)->nullable();             // the bill line, e.g. "Ceramic coating 9H"
            $table->date('service_on')->nullable();                      // last service date (bill date)
            $table->date('due_on')->index();
            $table->string('status', 10)->default('pending');            // pending | due | sent | done | skipped | cancelled
            $table->string('channel', 16)->nullable();                   // whatsapp | whatsapp_auto | call
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('message_log_id')->nullable();    // no FK: whatsapp may ship separately
            $table->timestamp('closed_at')->nullable();                  // done / skipped / cancelled
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->unsignedBigInteger('superseded_by_invoice_id')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'due_on']);
            $table->index(['customer_id', 'type']);
            $table->unique(['invoice_id', 'reminder_rule_id', 'offset_days'], 'service_reminders_once_per_bill');
        });

        $now = now();
        $rules = [
            ['Ceramic coating check-up', 'ceramic coating, ceramic, graphene coating', true, [180, 365],
                "Hello {name}, it has been a while since the {service} on your {car} ({date}). A quick check-up keeps the coating working and the shine fresh. Reply here and we will book a time that suits you.\n\nThank you, {shop}"],
            ['PPF inspection', 'ppf, paint protection film', true, [365],
                "Hello {name}, your {car} got {service} at our shop on {date}. It is time for the yearly PPF inspection: we check the edges and the film and fix anything small before it grows. Reply here to book a time.\n\nThank you, {shop}"],
            ['Wash / detailing', 'wash, detailing, rubbing, polishing', true, [30],
                "Hello {name}, it has been a month since the {service} on your {car}. Would you like to book the next wash or detailing? Reply here with a day that suits you.\n\nThank you, {shop}"],
            ['Wiper blades', 'wiper', false, [365],
                "Hello {name}, the wiper blades on your {car} were changed on {date}. After a year they usually start to streak, so it is a good time to check them before the rain. Reply here and we will keep a pair ready.\n\nThank you, {shop}"],
        ];
        foreach ($rules as [$name, $keywords, $servicesOnly, $offsets, $message]) {
            DB::table('reminder_rules')->insert([
                'name' => $name, 'match_type' => 'name', 'keywords' => $keywords, 'services_only' => $servicesOnly,
                'offsets_days' => json_encode($offsets), 'message' => $message, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reminders');
        Schema::dropIfExists('reminder_rules');
    }
};
