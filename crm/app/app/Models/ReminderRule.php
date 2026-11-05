<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which bill lines start a service check-up reminder, and after how many days (owner-editable).
 * Matches one product, a category (and its sub-categories), or words in the line name.
 */
class ReminderRule extends Model
{
    public const MATCH_TYPES = [
        'name' => 'Service or item name contains',
        'category' => 'Category',
        'product' => 'One product',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'offsets_days' => 'array',
            'services_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** @return list<string> lower-case keywords */
    public function keywordList(): array
    {
        return collect(explode(',', (string) $this->keywords))
            ->map(fn ($k) => mb_strtolower(trim($k)))->filter()->values()->all();
    }

    /** @return list<int> sorted, unique, positive */
    public function offsets(): array
    {
        return collect($this->offsets_days ?? [])->map(fn ($d) => (int) $d)->filter(fn ($d) => $d > 0)
            ->unique()->sort()->values()->all();
    }

    /** "180 and 365 days" */
    public function offsetsLabel(): string
    {
        $o = $this->offsets();
        if ($o === []) {
            return '—';
        }
        $last = array_pop($o);

        return ($o ? implode(', ', $o).' and ' : '').$last.' days';
    }

    public function matchLabel(): string
    {
        return match ($this->match_type) {
            'product' => 'Product: '.($this->product?->name ?? 'removed product'),
            'category' => 'Category: '.($this->category?->name ?? 'removed category'),
            default => 'Name has: '.implode(', ', $this->keywordList()),
        }.($this->services_only ? ' (services only)' : '');
    }

    /** Does this bill line start this rule? */
    public function matches(InvoiceItem $item): bool
    {
        $product = $item->product;
        if ($this->services_only && $product && ! $product->isService()) {
            return false;
        }
        if ($this->services_only && ! $product) {
            // A custom line has no type; let name rules still match it (fitting jobs are often typed in).
            if ($this->match_type !== 'name') {
                return false;
            }
        }

        return match ($this->match_type) {
            'product' => $this->product_id && $item->product_id === $this->product_id,
            'category' => $this->category_id && $product && (
                $product->category_id === $this->category_id
                || ($product->category && $product->category->parent_id === $this->category_id)
            ),
            default => $this->nameMatches((string) $item->name.' '.(string) $product?->name),
        };
    }

    public function nameMatches(string $text): bool
    {
        $text = mb_strtolower($text);
        foreach ($this->keywordList() as $k) {
            // Whole-word start match: "ppf" finds "Garware PPF bonnet", "wash" does not find "dishwasher".
            if (preg_match('/(^|[^\pL\pN])'.preg_quote($k, '/').'/u', $text)) {
                return true;
            }
        }

        return false;
    }
}
