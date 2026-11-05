<?php

namespace App\Models;

use App\Support\RegNo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A car in the workshop. Status only changes through App\Services\JobCards\JobCardService,
 * which checks the allowed moves, stamps the time and writes the timeline.
 */
class JobCard extends Model
{
    use LogsActivity;

    public const WAITING = 'waiting';

    public const WORKING = 'working';

    public const QC = 'qc';

    public const READY = 'ready';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::WAITING, self::WORKING, self::QC, self::READY, self::DELIVERED, self::CANCELLED];

    /** Statuses where the car is still in the shop. */
    public const OPEN = [self::WAITING, self::WORKING, self::QC, self::READY];

    public const LABELS = [
        self::WAITING => 'Waiting',
        self::WORKING => 'Working',
        self::QC => 'QC',
        self::READY => 'Ready',
        self::DELIVERED => 'Delivered',
        self::CANCELLED => 'Cancelled',
    ];

    public const FUEL_LEVELS = ['E', '1/4', '1/2', '3/4', 'F'];

    /** Column stamped when a card enters each status. */
    public const STAMPS = [
        self::WORKING => 'started_at',
        self::QC => 'qc_at',
        self::READY => 'ready_at',
        self::DELIVERED => 'delivered_at',
        self::CANCELLED => 'cancelled_at',
    ];

    protected $fillable = [
        'branch_id', 'customer_id', 'customer_car_id', 'odometer', 'fuel_level', 'promised_at',
        'technician_id', 'bay', 'notes', 'created_by',
    ];

    protected $attributes = ['status' => self::WAITING];

    protected function casts(): array
    {
        return [
            'odometer' => 'integer',
            'invoice_id' => 'integer',
            'promised_at' => 'datetime',
            'started_at' => 'datetime',
            'qc_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(CustomerCar::class, 'customer_car_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JobCardItem::class)->orderBy('position')->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(JobCardPhoto::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(JobCardEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeOpen(Builder $q): void
    {
        $q->whereIn('status', self::OPEN);
    }

    /** Open cards plus anything delivered since midnight IST: what the board shows. */
    public function scopeOnBoard(Builder $q): void
    {
        $q->where(fn (Builder $w) => $w->whereIn('status', self::OPEN)
            ->orWhere(fn (Builder $d) => $d->where('status', self::DELIVERED)->where('delivered_at', '>=', self::todayStart())));
    }

    public function scopeForTechnician(Builder $q, User $user): void
    {
        $q->where('technician_id', $user->id);
    }

    public static function todayStart(): Carbon
    {
        return now('Asia/Kolkata')->startOfDay()->utc();
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** Promised time has passed and the car is not ready yet. */
    public function isLate(): bool
    {
        return $this->promised_at !== null
            && in_array($this->status, [self::WAITING, self::WORKING, self::QC], true)
            && $this->promised_at->isPast();
    }

    /** "Ready by 5:30 pm", "Late 20 min", "Tomorrow 6 pm", "7 Nov, 11 am". Times are IST. */
    public function promiseText(): ?string
    {
        if (! $this->promised_at) {
            return null;
        }
        if ($this->isLate()) {
            $mins = (int) $this->promised_at->diffInMinutes(now(), true);

            return 'Late '.($mins < 60 ? $mins.' min' : intdiv($mins, 60).' h'.($mins % 60 ? ' '.($mins % 60).' min' : ''));
        }
        $t = $this->promised_at->copy()->timezone('Asia/Kolkata');
        $time = $t->minute === 0 ? $t->format('g a') : $t->format('g:i a');
        $today = now('Asia/Kolkata');

        return match (true) {
            $t->isSameDay($today) => 'Ready by '.$time,
            $t->isSameDay($today->copy()->addDay()) => 'Tomorrow '.$time,
            $t->isSameDay($today->copy()->subDay()) => 'Yesterday '.$time,
            default => $t->format('j M').', '.$time,
        };
    }

    public function plate(): ?string
    {
        return $this->car?->reg_no ? RegNo::pretty($this->car->reg_no) : null;
    }

    public static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/\(.*?\)/', '', (string) $name)), -1, PREG_SPLIT_NO_EMPTY);

        return strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2)))) ?: '?';
    }

    public function totalPaise(): int
    {
        return (int) $this->items->sum(fn (JobCardItem $i) => $i->qty * $i->price_paise);
    }

    /** "Creta" / "Hyundai Creta SX" — whatever is known about the car. */
    public function carLabel(bool $withMake = false): string
    {
        if (! $this->car) {
            return 'Car';
        }

        return $withMake ? $this->car->label() : trim($this->car->model ?: $this->car->label());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'technician_id', 'promised_at', 'bay', 'notes', 'invoice_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('jobcards');
    }
}
