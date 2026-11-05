<?php

namespace App\Models;

use App\Support\SearchText;
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
        'branch_id', 'sku', 'name', 'aliases', 'brand_id', 'category_id', 'type', 'hsn', 'gst_rate',
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
            if ($p->isDirty(['name', 'sku', 'aliases', 'brand_id']) || $p->search_text === null) {
                $p->search_text = $p->buildSearchText();
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

    /**
     * Every typed word must be the start of a word in the name, brand, SKU or aliases
     * ("creta mat" finds "7D floor mats (Creta)", "kavar" finds a seat cover aliased "seat kavar").
     */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        foreach (SearchText::words($term) as $w) {
            $q->where('search_text', 'like', '% '.SearchText::like($w).'%');
        }

        return $q;
    }

    /** Best match first: exact SKU, then name starting with the text, then best sellers. */
    public function scopeRankedFor(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term !== '') {
            $first = SearchText::words($term)[0] ?? '';
            $q->orderByRaw(
                'CASE WHEN LOWER(sku) = ? THEN 0 WHEN search_text LIKE ? THEN 1 ELSE 2 END',
                [mb_strtolower($term), ' '.SearchText::like($first).'%']
            );
        }

        return $q->orderByDesc('sold_count')->orderBy('name');
    }

    /** Aliases as a clean list. */
    public function aliasList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->aliases)), fn ($a) => $a !== ''));
    }

    public function buildSearchText(): string
    {
        $brand = $this->brand_id ? Brand::query()->whereKey($this->brand_id)->value('name') : null;
        $text = SearchText::normalize(implode(' ', [$this->name, $brand, $this->sku, str_replace(',', ' ', (string) $this->aliases)]));

        return ' '.$text.' ';
    }

    /** Normal price (MRP) minus our price, in paise; 0 when there is no MRP or no discount. */
    public function discountPaise(): int
    {
        return $this->mrp_paise && $this->mrp_paise > $this->price_paise ? $this->mrp_paise - $this->price_paise : 0;
    }

    /** Whole-number % off the MRP, rounded down so we never over-promise (6,499 → 4,999 = 23%). */
    public function discountPercent(): int
    {
        return $this->discountPaise() > 0 ? intdiv($this->discountPaise() * 100, $this->mrp_paise) : 0;
    }

    /** green / amber / red / service, for the stock pill. */
    public function stockState(): string
    {
        if (! $this->track_stock) {
            return 'service';
        }
        if ($this->on_hand <= 0) {
            return 'out';
        }

        return $this->on_hand <= $this->reorder_level ? 'low' : 'ok';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'aliases', 'price_paise', 'mrp_paise', 'cost_paise', 'gst_rate', 'is_active'])
            ->logOnlyDirty()->useLogName('products');
    }
}
