<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A party we buy stock from. Balance = opening + live bills − live payments (what we owe). */
class Supplier extends Model
{
    use LogsActivity;

    protected $fillable = [
        'branch_id', 'name', 'mobile', 'gstin', 'address', 'opening_balance_paise', 'notes', 'is_active', 'created_by',
    ];

    protected $attributes = ['opening_balance_paise' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['opening_balance_paise' => 'integer', 'is_active' => 'boolean'];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Adds billed_paise and paid_paise_sum (live rows only) for list pages. */
    public function scopeWithBalance(Builder $q): Builder
    {
        return $q->withSum(['purchases as billed_paise' => fn ($p) => $p->whereNull('cancelled_at')], 'total_paise')
            ->withSum(['payments as paid_paise_sum' => fn ($p) => $p->whereNull('cancelled_at')], 'amount_paise');
    }

    /** What we owe this supplier now. Negative = advance with them. */
    public function balancePaise(): int
    {
        $billed = $this->billed_paise ?? $this->purchases()->whereNull('cancelled_at')->sum('total_paise');
        $paid = $this->paid_paise_sum ?? $this->payments()->whereNull('cancelled_at')->sum('amount_paise');

        return (int) $this->opening_balance_paise + (int) $billed - (int) $paid;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'mobile', 'gstin', 'address', 'opening_balance_paise', 'is_active'])
            ->logOnlyDirty()->useLogName('suppliers');
    }
}
