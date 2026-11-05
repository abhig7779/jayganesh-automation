<?php

namespace App\Models;

use App\Support\Quotations\ShopClock;
use App\Support\RegNo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A price given to a customer. Every quotation is a lead with an owner (assigned_to) and a
 * follow-up schedule, so it can't be forgotten (UX-FLOWS §3.2). Money is integer paise.
 */
class Quotation extends Model
{
    use LogsActivity;

    public const OPEN = 'open';

    public const VISIT_BOOKED = 'visit_booked';

    public const WON = 'won';

    public const LOST = 'lost';

    public const EXPIRED = 'expired';

    public const STATUSES = [self::OPEN, self::VISIT_BOOKED, self::WON, self::LOST, self::EXPIRED];

    public const STATUS_LABELS = [
        self::OPEN => 'Open',
        self::VISIT_BOOKED => 'Visit booked',
        self::WON => 'Won',
        self::LOST => 'Lost',
        self::EXPIRED => 'Expired',
    ];

    /** Statuses that still need chasing. */
    public const ACTIVE = [self::OPEN, self::VISIT_BOOKED];

    public const LOST_REASONS = [
        'bought_elsewhere' => 'Bought elsewhere',
        'not_interested' => 'Not interested',
        'wrong_number' => 'Wrong number',
        'price' => 'Price too high',
        'no_reply' => 'No reply (expired)',
    ];

    protected $fillable = [
        'number', 'branch_id', 'customer_id', 'customer_car_id', 'lead_id', 'assigned_to', 'created_by',
        'status', 'valid_until', 'visit_on', 'taxable_paise', 'gst_paise', 'total_paise', 'savings_paise', 'version',
        'revised_from_id', 'revised_to_id', 'won_invoice_id', 'won_at', 'lost_reason', 'lost_note',
        'lost_at', 'expired_at', 'sent_at', 'source', 'notes', 'client_key',
    ];

    protected $attributes = ['status' => self::OPEN, 'version' => 1];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'visit_on' => 'date',
            'taxable_paise' => 'integer',
            'gst_paise' => 'integer',
            'total_paise' => 'integer',
            'savings_paise' => 'integer',
            'version' => 'integer',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'expired_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('position');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(CustomerCar::class, 'customer_car_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function revisedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revised_from_id');
    }

    public function revisedTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revised_to_id');
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'subject');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /** "Gleam 2.0 LED headlight, 7D floor mats +1 more" */
    public function itemsSummary(int $max = 2): string
    {
        $names = $this->items->pluck('name');
        $text = $names->take($max)->implode(', ');

        return $names->count() > $max ? $text.' +'.($names->count() - $max).' more' : $text;
    }

    public function carLabel(): string
    {
        return $this->car ? trim($this->car->model.' '.RegNo::pretty($this->car->reg_no)) : '';
    }

    /** Sales see their own; Owner/Admin see everything. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return self::seesAll($user) ? $q : $q->where('assigned_to', $user->id);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereIn('status', self::ACTIVE)->whereHas('followUps', fn (Builder $f) => $f
            ->where('status', FollowUp::PENDING)->whereDate('due_on', '<', ShopClock::today()));
    }

    /** Same rule as bills and Home: `reports.all_staff` sees everyone's work (audit L12). */
    public static function seesAll(User $user): bool
    {
        return $user->can('reports.all_staff');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'assigned_to', 'total_paise', 'valid_until', 'lost_reason', 'won_invoice_id'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('quotations');
    }
}
