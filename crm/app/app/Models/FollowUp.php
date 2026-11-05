<?php

namespace App\Models;

use App\Support\Quotations\ShopClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One planned contact with a customer about a quotation or a lead. Pending ones make up the
 * "Follow-ups today" list; overdue = pending and due before today (shop time).
 */
class FollowUp extends Model
{
    public const PENDING = 'pending';

    public const DONE = 'done';

    public const SKIPPED = 'skipped';

    public const OUTCOMES = [
        'coming_on' => 'Coming on…',
        'call_later' => 'Thinking / call later',
        'wants_better_price' => 'Wants a better price',
        'bought_elsewhere' => 'Bought elsewhere',
        'not_interested' => 'Not interested',
        'wrong_number' => 'Wrong number',
        'no_answer' => 'No answer',
    ];

    /** Outcomes that close the quote/lead as lost (the outcome is the reason). */
    public const LOST_OUTCOMES = ['bought_elsewhere', 'not_interested', 'wrong_number'];

    /** Outcomes that need a date picked. */
    public const DATED_OUTCOMES = ['coming_on', 'call_later'];

    protected $fillable = [
        'branch_id', 'subject_type', 'subject_id', 'customer_id', 'assigned_to', 'due_on', 'step', 'kind',
        'status', 'outcome', 'outcome_note', 'visit_on', 'channel', 'contacted_at', 'done_by', 'done_at', 'undo',
    ];

    protected $attributes = ['status' => self::PENDING, 'kind' => 'scheduled'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'visit_on' => 'date',
            'step' => 'integer',
            'contacted_at' => 'datetime',
            'done_at' => 'datetime',
            'undo' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_on->toDateString() < ShopClock::today();
    }

    public function outcomeLabel(): ?string
    {
        return $this->outcome ? (self::OUTCOMES[$this->outcome] ?? $this->outcome) : null;
    }

    /** "Day 3", "Visit", "Call back", "Retry" */
    public function stepLabel(): string
    {
        return match ($this->kind) {
            'visit' => 'Visit day',
            'call_later' => 'Call back',
            'retry' => 'Retry · Day '.$this->step,
            default => 'Day '.$this->step,
        };
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::PENDING);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->pending()->whereDate('due_on', '<', ShopClock::today());
    }

    public function scopeDueByToday(Builder $q): Builder
    {
        return $q->pending()->whereDate('due_on', '<=', ShopClock::today());
    }

    /**
     * Only the next pending follow-up of each quote/lead, so a customer appears once in the
     * list even when a retry and a planned step are both due.
     */
    public function scopeNextPerSubject(Builder $q): Builder
    {
        return $q->pending()->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('follow_ups as earlier')
            ->whereColumn('earlier.subject_type', 'follow_ups.subject_type')
            ->whereColumn('earlier.subject_id', 'follow_ups.subject_id')
            ->where('earlier.status', self::PENDING)
            ->where(fn ($w) => $w->whereColumn('earlier.due_on', '<', 'follow_ups.due_on')
                ->orWhere(fn ($x) => $x->whereColumn('earlier.due_on', 'follow_ups.due_on')->whereColumn('earlier.id', '<', 'follow_ups.id'))));
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return Quotation::seesAll($user) ? $q : $q->where('assigned_to', $user->id);
    }
}
