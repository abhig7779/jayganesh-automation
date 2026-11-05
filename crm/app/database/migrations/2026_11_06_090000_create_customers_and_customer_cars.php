<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('mobile', 20)->unique();           // E.164, e.g. +919823107676
            $table->string('alt_mobile', 20)->nullable()->index();
            $table->string('email', 150)->nullable();
            $table->string('area', 120)->nullable();           // area / city, e.g. Hadapsar, Pune
            $table->string('source', 30)->default('walk_in'); // see Customer::SOURCES
            $table->boolean('whatsapp_opt_in')->default(true);
            $table->timestamp('opted_out_at')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('total_spent_paise')->default(0);
            $table->timestamp('last_visit_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable()->index(); // no hard deletes

            $table->index(['branch_id', 'name']);
            $table->index('source');

            // FULLTEXT only exists on MySQL/MariaDB; SQLite (local, tests) gets the plain index above.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText('name');
            }
        });

        Schema::create('customer_cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('make', 60);
            $table->string('model', 80);
            $table->string('variant', 80)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('reg_no', 20)->nullable()->index(); // normalised: MH12AB1234
            $table->string('colour', 40)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['make', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cars');
        Schema::dropIfExists('customers');
    }
};
