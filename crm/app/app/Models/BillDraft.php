<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The one half-made bill a staff member has open (synced from the New bill screen). */
class BillDraft extends Model
{
    protected $fillable = ['user_id', 'draft_key', 'payload', 'item_count', 'total_paise'];

    protected $casts = ['payload' => 'array', 'item_count' => 'integer', 'total_paise' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
