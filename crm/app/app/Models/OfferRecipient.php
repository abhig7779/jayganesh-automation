<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferRecipient extends Model
{
    public const STATUS_LABELS = [
        'pending' => 'Not sent yet',
        'queued' => 'Queued',
        'sent' => 'Sent',
        'skipped' => 'Skipped',
        'failed' => 'Failed',
        'exported' => 'In broadcast list',
    ];

    protected $fillable = [
        'offer_id', 'customer_id', 'mobile', 'name', 'status', 'message_log_id', 'error', 'done_at', 'done_by',
    ];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
