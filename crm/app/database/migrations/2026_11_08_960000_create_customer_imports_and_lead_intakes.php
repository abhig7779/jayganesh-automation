<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer import (Excel / phone contacts) and lead intake (website form, Meta lead ads).
 *
 * customers.marketing_opt_in: null = never asked (customers added at the counter so far),
 * false = imported from an old list without marketing consent, true = agreed to offers.
 * whatsapp_opt_in stays the switch for service messages (bills, receipts, "car ready").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'marketing_opt_in')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('marketing_opt_in')->nullable()->after('whatsapp_opt_in');
            });
        }

        Schema::create('customer_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 32)->unique();            // storage/app/private/imports/customers/{token}.json
            $table->string('file_name', 150);
            $table->string('status', 12)->default('running'); // running | done
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->unsignedInteger('cars_added')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->json('problems')->nullable();             // [{line, reason}] rows skipped or partly saved
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);                    // api | meta_webhook
            $table->string('source', 30);                     // website | meta_lead_ad | instagram | other
            $table->string('external_id', 64)->nullable()->unique(); // Meta leadgen_id: never processed twice
            $table->boolean('merged')->default(false);        // added to an open lead from the last 7 days
            $table->string('mobile', 20)->index();
            $table->string('name', 120)->nullable();
            $table->string('interest', 300)->nullable();
            $table->string('car', 120)->nullable();
            $table->text('message')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('page_url', 300)->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_intakes');
        Schema::dropIfExists('customer_imports');
        if (Schema::hasColumn('customers', 'marketing_opt_in')) {
            Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('marketing_opt_in'));
        }
    }
};
