<?php

namespace App\Services\WhatsApp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The number connected from "Connect WhatsApp" (Embedded Signup). Lives in the WhatsApp module's
 * folder because crm/CONTRACTS.md gives this module Services/WhatsApp only.
 *
 * @property string $token decrypted on read; hidden from arrays/JSON
 */
class WhatsAppConnection extends Model
{
    protected $table = 'whatsapp_connections';

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'is_on_biz_app' => 'boolean',
            'signup_event' => 'array',
            'subscribed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /** The live connection, or null (also before the migration has run). */
    public static function current(): ?self
    {
        static $hasTable = null;
        $hasTable ??= Schema::hasTable('whatsapp_connections');
        if (! $hasTable) {
            return null;
        }

        return self::query()->where('status', 'connected')->latest('id')->first();
    }

    public function isCoexistence(): bool
    {
        return (bool) $this->is_on_biz_app;
    }
}
