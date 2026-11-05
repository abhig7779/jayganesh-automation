<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** End-of-day drawer count (UX-FLOWS.md §3.8). One row per branch per day. */
class CashClose extends Model
{
    use LogsActivity;

    protected $fillable = [
        'branch_id', 'date', 'opening_float_paise', 'cash_in_paise', 'cash_out_paise',
        'expected_cash_paise', 'counted_cash_paise', 'difference_paise', 'note', 'summary', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'summary' => 'array',
            'opening_float_paise' => 'integer',
            'cash_in_paise' => 'integer',
            'cash_out_paise' => 'integer',
            'expected_cash_paise' => 'integer',
            'counted_cash_paise' => 'integer',
            'difference_paise' => 'integer',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Store the shop day as a plain Y-m-d string on every driver, so date range filters compare cleanly. */
    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['date', 'expected_cash_paise', 'counted_cash_paise', 'difference_paise', 'note'])
            ->logOnlyDirty()
            ->useLogName('cash_close');
    }
}
