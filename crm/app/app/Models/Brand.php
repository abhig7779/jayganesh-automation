<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = ['name'];

    protected static function booted(): void
    {
        // Brand names are part of every product's search words.
        static::updated(function (Brand $b) {
            if ($b->wasChanged('name')) {
                $b->products()->each(fn (Product $p) => $p->forceFill(['search_text' => $p->buildSearchText()])->saveQuietly());
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
