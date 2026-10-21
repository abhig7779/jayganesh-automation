<?php
/**
 * Renders the email templates with sample data. Local use only: runs from the CLI or from
 * a browser on 127.0.0.1 / ::1 (e.g. `php -S 127.0.0.1:8099` in this folder). Sends nothing.
 *
 *   php preview.php                  write every template to previews/*.html (+ .txt)
 *   php preview.php lead-contact     print one template's HTML to stdout
 *   php preview.php lead-contact txt print its plain-text part instead
 *
 * Names: lead-contact, lead-partnership, lead-vendor, auto-reply.
 * Do not upload this file (.htaccess denies it anyway).
 */
declare(strict_types=1);

$cli = PHP_SAPI === 'cli';
if (!$cli && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}

define('JG_API', true);
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

$meta = ['id' => '20261022-183012-a1b2c3', 'time_ist' => 'Thu, 22 Oct 2026, 6:30 PM IST', 'ip' => '203.0.113.42'];

$samples = [
    'contact' => [
        'form' => 'contact',
        'name' => 'Rahul Patil',
        'phone' => '+919876543210',
        'email' => 'rahul.patil@example.com',
        'fields' => ['car' => 'Hyundai Creta 2024', 'service' => 'Ceramic / graphene coating', 'city' => 'Hadapsar'],
        'message' => "Hi, I bought the car last month. Want ceramic coating and maybe seat covers.\nWhen can I bring it in?",
        'page' => '/detailing-studio/',
    ],
    'partnership' => [
        'form' => 'partnership',
        'name' => 'Sneha Kulkarni',
        'phone' => '+919822012345',
        'email' => '',
        'fields' => ['company' => 'Kulkarni Motors', 'partnerType' => 'Car dealership / showroom', 'volume' => '20–30 cars per month', 'city' => 'Pune'],
        'message' => 'We deliver new cars every week and want a detailing partner for delivery-day packages.',
        'page' => '/partnership/',
    ],
    'vendor' => [
        'form' => 'vendor',
        'name' => 'Amit Shah',
        'phone' => '+917020011223',
        'email' => 'amit@example.com',
        'fields' => ['company' => 'ShieldPro Films Pvt Ltd', 'category' => 'PPF & films', 'brands' => 'ShieldPro, ClearGuard', 'website' => 'https://example.com', 'gst' => '27ABCDE1234F1Z5', 'city' => 'Mumbai'],
        'message' => '',
        'page' => '/vendors/',
    ],
];

$build = static function (string $name) use ($samples, $meta): array {
    return match ($name) {
        'lead-contact' => jg_build_shop_email($samples['contact'], $meta),
        'lead-partnership' => jg_build_shop_email($samples['partnership'], $meta),
        'lead-vendor' => jg_build_shop_email($samples['vendor'], $meta),
        'auto-reply' => jg_build_auto_reply($samples['contact']),
        default => throw new InvalidArgumentException('Unknown template: ' . $name),
    };
};
$names = ['lead-contact', 'lead-partnership', 'lead-vendor', 'auto-reply'];

if ($cli) {
    $which = $argv[1] ?? 'all';
    if ($which === 'all') {
        $out = __DIR__ . '/previews';
        if (!is_dir($out)) {
            mkdir($out, 0755, true);
        }
        foreach ($names as $n) {
            [$subject, $html, $text] = $build($n);
            file_put_contents("$out/$n.html", $html);
            file_put_contents("$out/$n.txt", "Subject: $subject\n\n$text");
            fwrite(STDOUT, "previews/$n.html  ($subject)\n");
        }
        exit(0);
    }
    [$subject, $html, $text] = $build($which);
    echo (($argv[2] ?? '') === 'txt') ? "Subject: $subject\n\n$text" : $html;
    exit(0);
}

$which = is_string($_GET['t'] ?? null) ? $_GET['t'] : '';
if (!in_array($which, $names, true)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Email previews</title><ul style="font:16px system-ui;line-height:2">';
    foreach ($names as $n) {
        echo '<li><a href="?t=' . $n . '">' . $n . '</a> &middot; <a href="?t=' . $n . '&amp;f=txt">text</a></li>';
    }
    echo '</ul>';
    exit;
}
[$subject, $html, $text] = $build($which);
if (($_GET['f'] ?? '') === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Subject: $subject\n\n$text";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}
