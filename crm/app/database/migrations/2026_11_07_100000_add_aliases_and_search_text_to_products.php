<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products module (crm/CONTRACTS.md block 2026_11_07_10xxxx).
 * - aliases: comma-separated other names staff type ("gleam, dhc led, h4 led").
 * - search_text: lower-case words of name + brand + SKU + aliases, each preceded by a space,
 *   so "every typed word is the start of some word" is a plain LIKE '% word%' on one column.
 *   Product::saving() keeps it in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('aliases')->nullable()->after('name');
            $table->text('search_text')->nullable()->after('aliases');
        });

        Product::query()->with('brand')->each(
            fn (Product $p) => $p->forceFill(['search_text' => $p->buildSearchText()])->saveQuietly()
        );
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['aliases', 'search_text']);
        });
    }
};
