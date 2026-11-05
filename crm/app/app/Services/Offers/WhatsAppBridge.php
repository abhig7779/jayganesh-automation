<?php

namespace App\Services\Offers;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;

/**
 * The only place offers talk to the WhatsApp module (CONTRACTS.md hand-off 4). The module is
 * built on another branch, so everything is guarded with class_exists: without it, offers run
 * in free mode only (broadcast list export and the one-by-one sender).
 */
class WhatsAppBridge
{
    /** Overridable in tests so a fake class can stand in for the real module. */
    public static string $class = 'App\\Services\\WhatsApp\\WhatsApp';

    public function moduleInstalled(): bool
    {
        return class_exists(static::$class);
    }

    /** Paid automatic sending is offered only when the module exists AND is in Cloud API mode. */
    public function automaticAvailable(): bool
    {
        if (! $this->moduleInstalled()) {
            return false;
        }
        $wa = app(static::$class);

        return method_exists($wa, 'isAutomatic') && (bool) $wa->isAutomatic();
    }

    /**
     * Approved MARKETING templates, if the module can list them; otherwise [] and the form asks
     * for the template name by hand.
     *
     * @return array<string,string> name => label
     */
    public function marketingTemplates(): array
    {
        if (! $this->moduleInstalled()) {
            return [];
        }
        $wa = app(static::$class);
        if (! method_exists($wa, 'marketingTemplates')) {
            return [];
        }
        $list = (array) $wa->marketingTemplates();

        return array_is_list($list) ? array_combine($list, $list) : $list;
    }

    /** Returns the MessageLog id (or null when the module returns nothing usable). */
    public function sendTemplate(string $toE164, string $template, array $params, ?Model $related = null): ?int
    {
        $log = app(static::$class)->sendTemplate($toE164, $template, $params, null, null, $related);

        return is_object($log) && isset($log->id) ? (int) $log->id : null;
    }

    /** A wa.me link with the text filled in; uses the module's builder when present. */
    public function manualLink(string $toE164, string $text): string
    {
        if ($this->moduleInstalled() && method_exists(static::$class, 'manualLink')) {
            return app(static::$class)->manualLink($toE164, $text);
        }

        return 'https://wa.me/'.preg_replace('/\D+/', '', $toE164).'?text='.rawurlencode($text);
    }

    /** Meta's marketing rate per message, in paise (Settings: offers.marketing_rate_paise). */
    public static function ratePaise(): int
    {
        $v = Setting::get('offers.marketing_rate_paise');

        return $v === null || $v === '' ? 88 : max(0, (int) $v);
    }

    public static function estimateCostPaise(int $count): int
    {
        return $count * self::ratePaise();
    }
}
