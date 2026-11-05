<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Shared product schema (crm/CONTRACTS.md). Billing, quotations and stock all build on it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();
            $table->unique(['parent_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // null = all branches
            $table->string('sku', 40)->nullable()->unique();
            $table->string('name', 160);
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10)->default('product');      // product | service (no stock)
            $table->string('hsn', 10)->nullable();                // HSN for goods, SAC for services
            $table->decimal('gst_rate', 5, 2)->default(18);       // 0, 5, 12, 18, 28
            $table->boolean('price_includes_gst')->default(true); // shop prices are usually GST-inclusive
            $table->unsignedBigInteger('mrp_paise')->nullable();
            $table->unsignedBigInteger('price_paise');            // selling price
            $table->unsignedBigInteger('cost_paise')->nullable(); // last purchase cost (hidden from Sales)
            $table->string('unit', 10)->default('pcs');
            $table->boolean('track_stock')->default(true);
            $table->integer('on_hand')->default(0);              // cached sum of stock_moves (may go negative)
            $table->integer('reorder_level')->default(0);
            $table->string('shelf', 40)->nullable();
            $table->string('warranty', 80)->nullable();
            $table->json('compatible_cars')->nullable();          // ["Hyundai Creta", "Kia Seltos"]
            $table->unsignedInteger('sold_count')->default(0);    // drives "Popular" chips
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'sold_count']);
            $table->index('name');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products ADD FULLTEXT products_name_fulltext (name, sku)');
        }

        Schema::create('stock_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('qty');                                // + in, − out
            $table->string('type', 20);                            // opening, purchase, sale, sale_cancel, return, adjustment
            $table->nullableMorphs('reference');                   // the invoice / purchase that caused it
            $table->unsignedBigInteger('unit_cost_paise')->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_moves');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
