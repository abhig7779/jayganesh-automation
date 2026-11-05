<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One download of a Meta Custom Audience customer list (privacy audit, CRM-PLAN.md §6). */
class AudienceExport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'segment_id', 'segment_name', 'filters', 'format', 'fields', 'row_count', 'ip'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'fields' => 'array', 'row_count' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
