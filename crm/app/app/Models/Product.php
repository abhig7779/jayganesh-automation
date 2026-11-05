<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Shared contract (crm/CONTRACTS.md). Money columns are integer paise.
 * Change stock only through App\Services\Stock\StockService, never by writing on_hand directly.
 */
class Product extends Model
{
    use HasFactory, LogsActivity;

    public const TYPE_PRODUCT = 'product';

    public const TYPE_SERVICE = 'service';

    protected $fillable = [
        'branch_id', 'sku', 'name', 'brand_id', 'category_id', 'type', 'hsn', 'gst_rate',
        'price_includes_gst', 'mrp_paise', 'price_paise', 'cost_paise', 'unit', 'track_stock',
        'reorder_level', 'shelf', 'warranty', 'compatible_cars', 'notes', 'is_active', 'created_by',
    ];

    protected $attributes = [
        'type' => self::TYPE_PRODUCT,
        'gst_rate' => 18,
        'price_includes_gst' => true,
        'unit' => 'pcs',
        'track_stock' => true,
        'on_hand' => 0,
        'reorder_level' => 0,
        'sold_count' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'gst_rate' => 'decimal:2',
            'price_includes_gst' => 'boolean',
            'mrp_paise' => 'integer',
            'price_paise' => 'integer',
            'cost_paise' => 'integer',
            'track_stock' => 'boolean',
            'on_hand' => 'integer',
            'reorder_level' => 'integer',
            'sold_count' => 'integer',
            'compatible_cars' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if ($p->type === self::TYPE_SERVICE) {
                $p->track_stock = false;
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMoves(): HasMany
    {
        return $this->hasMany(StockMove::class);
    }

    public function isService(): bool
    {
        return $this->type === self::TYPE_SERVICE;
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->on_hand <= $this->reorder_level;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Name / SKU / brand contains every typed word ("creta mat" finds "7D floor mats (Creta)"). */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $words = preg_split('/\s+/', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $w) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $w).'%';
            $q->where(fn (Builder $x) => $x->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', $like)));
        }

        return $q;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'price_paise', 'mrp_paise', 'cost_paise', 'gst_rate', 'is_active'])
            ->logOnlyDirty()->useLogName('products');
    }
}
