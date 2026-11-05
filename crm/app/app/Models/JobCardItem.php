<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of work on a job card. price_paise is the unit price (GST-inclusive, like products). */
class JobCardItem extends Model
{
    protected $fillable = ['job_card_id', 'product_id', 'description', 'qty', 'price_paise', 'position'];

    protected $attributes = ['qty' => 1, 'price_paise' => 0, 'done' => false];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price_paise' => 'integer',
            'done' => 'boolean',
            'done_at' => 'datetime',
        ];
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
