<?php

namespace App\Services\Offers;

use App\Models\Customer;
use Illuminate\Support\Str;

/** Fills {name} and {car} in an offer text for one customer. */
final class OfferMessage
{
    public const PLACEHOLDERS = ['{name}' => 'first name', '{car}' => 'car model'];

    public static function render(string $text, ?Customer $customer): string
    {
        $values = self::values($customer);

        return (string) preg_replace_callback('/\{\{?\s*(name|car)\s*\}?\}/i', fn ($m) => $values[strtolower($m[1])], $text);
    }

    /** For a broadcast list, where one text goes to everyone: "Hi {name}" → "Hi there". */
    public static function generic(string $text): string
    {
        $values = ['name' => 'there', 'car' => 'car'];

        return (string) preg_replace_callback('/\{\{?\s*(name|car)\s*\}?\}/i', fn ($m) => $values[strtolower($m[1])], $text);
    }

    /** @return array{name:string, car:string} */
    public static function values(?Customer $customer): array
    {
        if (! $customer) {
            return ['name' => 'Rahul', 'car' => 'Creta'];
        }
        $first = trim(Str::before(trim((string) $customer->name), ' '));
        $car = $customer->relationLoaded('cars')
            ? $customer->cars->sortByDesc('id')->first()
            : $customer->cars()->latest('id')->first();

        return [
            'name' => $first !== '' ? Str::title($first) : 'there',
            'car' => $car?->model ? (string) $car->model : 'car',
        ];
    }

    /** WhatsApp counts characters, not bytes; emoji count as 1–2. */
    public static function length(string $text): int
    {
        return mb_strlen($text);
    }

    public static function waLink(string $e164, string $text): string
    {
        $bridge = app(WhatsAppBridge::class);

        return $bridge->manualLink($e164, $text);
    }
}
