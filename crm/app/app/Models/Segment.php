<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved set of customer filters (see App\Services\Offers\SegmentBuilder). */
class Segment extends Model
{
    protected $fillable = ['branch_id', 'name', 'filters', 'created_by'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
