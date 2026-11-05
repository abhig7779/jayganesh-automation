<?php

namespace App\Models;

use App\Support\SettingsSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Setting extends Model
{
    use LogsActivity;

    protected $fillable = ['branch_id', 'key', 'value', 'updated_by'];

    protected $casts = ['value' => 'json'];

    private const CACHE_KEY = 'crm.settings';

    /** Read a setting: branch value first, then the shop-wide value, then the schema default. */
    public static function get(string $key, mixed $default = null, ?int $branchId = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => self::query()
            ->get(['branch_id', 'key', 'value'])
            ->mapWithKeys(fn ($s) => [($s->branch_id ?? 0).'|'.$s->key => $s->value])
            ->all());

        if ($branchId && array_key_exists("$branchId|$key", $all)) {
            return $all["$branchId|$key"];
        }
        if (array_key_exists("0|$key", $all)) {
            return $all["0|$key"];
        }

        return $default ?? (SettingsSchema::fields()[$key]['default'] ?? null);
    }

    public static function put(string $key, mixed $value, ?int $branchId = null, ?int $userId = null): void
    {
        $setting = self::query()->firstOrNew(['branch_id' => $branchId, 'key' => $key]);
        $setting->fill(['value' => $value, 'updated_by' => $userId])->save();
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['key', 'value'])->logOnlyDirty()->useLogName('settings');
    }
}
