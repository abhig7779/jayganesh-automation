<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Money we paid a supplier, optionally against one bill. */
class SupplierPayment extends Model
{
    use LogsActivity;

    public const MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank', 'cheque' => 'Cheque'];

    protected $fillable = [
        'branch_id', 'supplier_id', 'purchase_id', 'date', 'amount_paise', 'mode', 'reference', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount_paise' => 'integer', 'cancelled_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('cancelled_at');
    }

    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['supplier_id', 'purchase_id', 'date', 'amount_paise', 'mode', 'reference', 'cancelled_at'])
            ->logOnlyDirty()->useLogName('supplier_payments');
    }
}
