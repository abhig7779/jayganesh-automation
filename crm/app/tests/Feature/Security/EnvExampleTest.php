<?php

/*
 * Audit M5: every setting the config reads must be named in .env.production.example
 * (uncommented, or commented as "# KEY=" when the default is right), and the example must not
 * carry names nothing reads (that is how WHATSAPP_ACCESS_TOKEN silently left sending off).
 */

function envKeysReadBy(string $glob): array
{
    $keys = [];
    foreach (glob($glob) as $file) {
        preg_match_all("/env\\(\\s*['\"]([A-Z0-9_]+)['\"]/", file_get_contents($file), $m);
        $keys = array_merge($keys, $m[1]);
    }

    return array_values(array_unique($keys));
}

function envKeysListedIn(string $file, bool $withCommented): array
{
    $pattern = $withCommented ? '/^#?\s?([A-Z][A-Z0-9_]*)=/m' : '/^([A-Z][A-Z0-9_]*)=/m';
    preg_match_all($pattern, file_get_contents($file), $m);

    return array_values(array_unique($m[1]));
}

it('names every env() setting from config/*.php in .env.production.example', function () {
    $listed = envKeysListedIn(base_path('.env.production.example'), true);
    $missing = array_values(array_diff(envKeysReadBy(config_path('*.php')), $listed));

    expect($missing)->toBe([]);
});

it('has no settings in the env examples that nothing reads', function (string $file) {
    $read = array_merge(
        envKeysReadBy(config_path('*.php')),
        envKeysReadBy(base_path('vendor/laravel/framework/config/*.php')),
        ['PHP_CLI_SERVER_WORKERS'], // read by `php artisan serve`
    );
    $unknown = array_values(array_filter(
        array_diff(envKeysListedIn(base_path($file), false), $read),
        fn ($k) => ! str_starts_with($k, 'VITE_'),
    ));

    expect($unknown)->toBe([]);
})->with(['.env.production.example', '.env.example']);

it('uses the WhatsApp names config/whatsapp.php reads', function () {
    $example = file_get_contents(base_path('.env.production.example'));

    foreach (['WHATSAPP_TOKEN=', 'WHATSAPP_WABA_ID=', 'WHATSAPP_GRAPH_VERSION='] as $line) {
        expect($example)->toContain($line);
    }
    expect($example)->not->toContain('WHATSAPP_ACCESS_TOKEN')
        ->not->toContain('WHATSAPP_BUSINESS_ACCOUNT_ID')
        ->not->toContain('WHATSAPP_API_VERSION');
});
