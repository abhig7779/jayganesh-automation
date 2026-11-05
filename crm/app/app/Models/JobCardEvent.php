<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The job card timeline: every status change, assignment, tick, photo and message, with who and when. */
class JobCardEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['job_card_id', 'type', 'from_status', 'to_status', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function summary(): string
    {
        if ($this->type === 'created') {
            return 'Car in, job card opened';
        }
        if ($this->type === 'status') {
            $text = 'Moved to '.(JobCard::LABELS[$this->to_status] ?? $this->to_status);

            return $this->note ? "$text · {$this->note}" : $text;
        }

        return (string) $this->note;
    }
}
