<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Workshop job cards: car in → working → QC → ready → delivered (UX-FLOWS §3.4). */
return new class extends Migration
{
    public function up(): void
    {
        // One row per branch + financial year; locked FOR UPDATE while a number is taken, so
        // two counters creating job cards at once never share or skip a number.
        Schema::create('job_card_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('fy', 4); // "2627" for Apr 2026 – Mar 2027
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'fy']);
        });

        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique(); // JC-2627-0001
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_car_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('odometer')->nullable();
            $table->string('fuel_level', 8)->nullable(); // E, 1/4, 1/2, 3/4, F
            $table->string('status', 12)->default('waiting')->index();
            $table->timestamp('promised_at')->nullable()->index();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bay', 20)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable()->index(); // billing lives on another branch: no FK
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('qc_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['technician_id', 'status']);
        });

        Schema::create('job_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 200);
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('price_paise')->default(0); // unit price, GST-inclusive like products
            $table->boolean('done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('job_card_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 10)->default('before'); // before | after | other
            $table->string('path');                          // on the private "local" disk
            $table->string('thumb_path')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->uuid('client_uuid')->nullable();         // lets a phone retry an upload without duplicates
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['job_card_id', 'client_uuid']);
        });

        Schema::create('job_card_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);            // created, status, assigned, item_done, item_undone, photo, whatsapp, note, billed
            $table->string('from_status', 12)->nullable();
            $table->string('to_status', 12)->nullable();
            $table->string('note', 300)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['job_card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_card_events');
        Schema::dropIfExists('job_card_photos');
        Schema::dropIfExists('job_card_items');
        Schema::dropIfExists('job_cards');
        Schema::dropIfExists('job_card_sequences');
    }
};
