<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A photo on the private disk. Never linked directly: served by jobcards.photos.show after a permission check. */
class JobCardPhoto extends Model
{
    public const STAGES = ['before', 'after', 'other'];

    public const DISK = 'local';

    protected $fillable = ['job_card_id', 'stage', 'path', 'thumb_path', 'width', 'height', 'client_uuid', 'uploaded_by'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer'];
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(bool $thumb = false): string
    {
        return route('jobcards.photos.show', ['jobCard' => $this->job_card_id, 'photo' => $this->id] + ($thumb ? ['thumb' => 1] : []));
    }
}
