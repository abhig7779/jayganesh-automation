<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One enquiry as it arrived (website form or Meta lead ad), with its UTM tags. Feeds a Lead. */
class LeadIntake extends Model
{
    protected $fillable = [
        'lead_id', 'channel', 'source', 'external_id', 'merged', 'mobile', 'name', 'interest', 'car',
        'message', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'page_url',
        'ip', 'token_id', 'payload',
    ];

    protected function casts(): array
    {
        return ['merged' => 'boolean', 'payload' => 'array'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
