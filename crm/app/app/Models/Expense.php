<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** One money-out entry in the expense book. Cancelled, never deleted. */
class Expense extends Model
{
    use LogsActivity;

    public const MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank'];

    protected $fillable = [
        'branch_id', 'date', 'expense_category_id', 'amount_paise', 'mode', 'paid_to', 'note',
        'receipt_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount_paise' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Store the shop day as a plain Y-m-d string on every driver, so date range filters compare cleanly. */
    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['date', 'expense_category_id', 'amount_paise', 'mode', 'paid_to', 'note', 'cancelled_at'])
            ->logOnlyDirty()
            ->useLogName('expenses');
    }
}
