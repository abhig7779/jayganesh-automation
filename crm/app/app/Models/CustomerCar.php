<?php

namespace App\Models;

use App\Support\RegNo;
use Database\Factories\CustomerCarFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CustomerCar extends Model
{
    /** @use HasFactory<CustomerCarFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id', 'make', 'model', 'variant', 'year', 'reg_no', 'colour', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['year' => 'integer'];
    }

    protected function regNo(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => RegNo::normalize($v));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function label(): string
    {
        return trim("{$this->make} {$this->model} {$this->variant}");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer_id', 'make', 'model', 'variant', 'year', 'reg_no', 'colour', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customers');
    }
}
