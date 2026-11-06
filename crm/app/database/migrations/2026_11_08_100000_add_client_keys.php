<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency keys (audit M4 / L2): each form carries a random client_key, so a double click
 * or a retried request returns the row the first one saved instead of making a second.
 */
return new class extends Migration
{
    private const TABLES = ['payments', 'expenses', 'job_cards', 'quotations'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->string('client_key', 64)->nullable();
                // client_key first: MySQL would otherwise adopt a branch_id-led index for the branch foreign key
                // and refuse to drop it on rollback.
                $t->unique(['client_key', 'branch_id'], $table.'_client_key_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table.'_client_key_unique');
                $t->dropColumn('client_key');
            });
        }
    }
};
