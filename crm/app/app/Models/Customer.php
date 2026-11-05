<?php

namespace App\Models;

use App\Support\Phone;
use App\Support\RegNo;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, LogsActivity;

    public const SOURCES = ['walk_in', 'whatsapp_ad', 'instagram', 'google', 'referral', 'other'];

    public const SOURCE_LABELS = [
        'walk_in' => 'Walk-in',
        'whatsapp_ad' => 'WhatsApp ad',
        'instagram' => 'Instagram',
        'google' => 'Google',
        'referral' => 'Referral',
        'other' => 'Other',
    ];

    protected $fillable = [
        'branch_id', 'name', 'mobile', 'alt_mobile', 'email', 'area', 'source',
        'whatsapp_opt_in', 'tags', 'notes', 'created_by',
    ];

    protected $attributes = [
        'source' => 'walk_in',
        'whatsapp_opt_in' => true,
        'total_spent_paise' => 0,
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_opt_in' => 'boolean',
            'opted_out_at' => 'datetime',
            'tags' => 'array',
            'total_spent_paise' => 'integer',
            'last_visit_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Opting out stamps the time (CRM-PLAN.md §6 privacy); opting back in clears it.
        static::saving(function (Customer $c) {
            if ($c->isDirty('whatsapp_opt_in')) {
                $c->opted_out_at = $c->whatsapp_opt_in ? null : now();
            }
        });
    }

    protected function mobile(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => Phone::normalize($v));
    }

    protected function altMobile(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => Phone::normalize($v));
    }

    public function cars(): HasMany
    {
        return $this->hasMany(CustomerCar::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Counter search: matches mobile digits (even a partial "07676"), the name, or a car's
     * reg no typed any way ("MH 12 ab 1234" finds MH12AB1234).
     */
    public function scopeSearch(Builder $query, string $q): void
    {
        $q = trim($q);
        if ($q === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $q);
        if (strlen($digits) >= 10) {
            // A full number typed with 0 / 91 prefix: compare on the national 10 digits.
            $digits = substr($digits, -10);
        }
        $reg = RegNo::normalize($q);

        $query->where(function (Builder $w) use ($q, $digits, $reg) {
            $w->where('name', 'like', '%'.self::escapeLike($q).'%');

            if (strlen($digits) >= 4) {
                $w->orWhere('mobile', 'like', '%'.$digits.'%')
                    ->orWhere('alt_mobile', 'like', '%'.$digits.'%');
            }
            if ($reg !== null && strlen($reg) >= 3) {
                $w->orWhereHas('cars', fn (Builder $c) => $c->where('reg_no', 'like', '%'.$reg.'%'));
            }
        });
    }

    /** Wildcards are dropped rather than escaped: SQLite and MySQL disagree on the default LIKE escape. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], '', $value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'mobile', 'alt_mobile', 'email', 'area', 'source', 'whatsapp_opt_in',
                'opted_out_at', 'tags', 'notes', 'branch_id', 'archived_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customers');
    }
}
