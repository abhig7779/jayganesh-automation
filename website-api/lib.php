<?php
/**
 * Jay Ganesh website API: helpers (validation, storage, templates, mail).
 * Included only; never served directly.
 */
declare(strict_types=1);

if (!defined('JG_API')) {
    http_response_code(404);
    exit;
}

const JG_TEMPLATE_DIR = __DIR__ . '/templates';

/** Per-form definitions. fields: key => [label, max length, required]. */
function jg_forms(): array
{
    $city = ['Area / city', 60, false];
    return [
        'contact' => [
            'label' => 'Contact',
            'title' => 'New contact enquiry',
            'template' => 'lead-contact',
            'message_required' => true,
            'fields' => [
                'car' => ['Car model', 80, false],
                'service' => ['Service', 80, false],
                'city' => $city,
            ],
        ],
        'partnership' => [
            'label' => 'Partnership',
            'title' => 'New partnership enquiry',
            'template' => 'lead-partnership',
            'message_required' => false,
            'fields' => [
                'company' => ['Company or brand', 120, true],
                'partnerType' => ['Partner type', 60, false],
                'volume' => ['Expected volume', 60, false],
                'city' => $city,
            ],
        ],
        'vendor' => [
            'label' => 'Vendor',
            'title' => 'New vendor introduction',
            'template' => 'lead-vendor',
            'message_required' => false,
            'fields' => [
                'company' => ['Company name', 120, true],
                'category' => ['Product category', 80, false],
                'brands' => ['Brands supplied', 200, false],
                'website' => ['Website', 200, false],
                'gst' => ['GST number', 15, false],
                'city' => $city,
            ],
        ],
    ];
}

// ---------------------------------------------------------------------------
// HTTP helpers
// ---------------------------------------------------------------------------

function jg_json(int $status, array $body, array $headers = []): never
{
    http_response_code($status);
    foreach ($headers as $h) {
        header($h);
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** "scheme://host[:port]" of a URL, lowercased, or null. */
function jg_origin_of(string $url): ?string
{
    $p = parse_url(trim($url));
    if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
        return null;
    }
    $o = strtolower($p['scheme']) . '://' . strtolower($p['host']);
    if (isset($p['port'])) {
        $o .= ':' . (int) $p['port'];
    }
    return $o;
}

/**
 * Returns [allowedOrigin|null, fromOriginHeader(bool)].
 * Origin header wins; a missing Origin is accepted only when the Referer is on an allowed origin.
 */
function jg_check_origin(): array
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (is_string($origin) && $origin !== '') {
        $o = jg_origin_of($origin);
        return [($o !== null && in_array($o, JG_ALLOWED_ORIGINS, true)) ? $o : null, true];
    }
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if (is_string($ref) && $ref !== '') {
        $o = jg_origin_of($ref);
        if ($o !== null && in_array($o, JG_ALLOWED_ORIGINS, true)) {
            return [$o, false];
        }
    }
    return [null, false];
}

function jg_client_ip(): string
{
    // REMOTE_ADDR only: forwarded headers are client-controlled. If the API is ever put
    // behind Cloudflare, switch this to HTTP_CF_CONNECTING_IP.
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

// ---------------------------------------------------------------------------
// Text cleaning and validation
// ---------------------------------------------------------------------------

function jg_strlen(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./us', $s);
}

function jg_substr(string $s, int $len): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $len, 'UTF-8');
    }
    return preg_match('/^.{0,' . $len . '}/us', $s, $m) ? $m[0] : '';
}

/**
 * Trim, force valid UTF-8, strip control / invisible direction characters, collapse spaces
 * and cap the length. Single-line values lose all line breaks.
 */
function jg_clean(mixed $v, int $max, bool $multiline = false): string
{
    if (!is_string($v)) {
        return '';
    }
    if (preg_match('//u', $v) !== 1) {
        $v = function_exists('mb_convert_encoding') ? (string) mb_convert_encoding($v, 'UTF-8', 'UTF-8') : '';
        if (preg_match('//u', $v) !== 1) {
            return '';
        }
    }
    $v = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $v);
    // C0/C1 controls except \n, zero-width space, BOM, bidi overrides, Unicode line/paragraph separators.
    $v = preg_replace('/[\x{0}-\x{8}\x{B}-\x{1F}\x{7F}-\x{9F}\x{200B}\x{202A}-\x{202E}\x{2028}\x{2029}\x{2066}-\x{2069}\x{FEFF}]/u', '', $v) ?? '';
    if ($multiline) {
        $v = preg_replace('/ +\n/', "\n", $v) ?? '';
        $v = preg_replace("/\n{3,}/", "\n\n", $v) ?? '';
    } else {
        $v = str_replace("\n", ' ', $v);
    }
    $v = trim(preg_replace('/ {2,}/', ' ', $v) ?? '');
    if (jg_strlen($v) > $max) {
        $v = rtrim(jg_substr($v, $max));
    }
    return $v;
}

/** Normalises an Indian mobile number to +91XXXXXXXXXX, or null. */
function jg_normalize_phone(mixed $raw): ?string
{
    if (!is_string($raw)) {
        return null;
    }
    $d = preg_replace('/[\s\-().]/u', '', trim($raw)) ?? '';
    if (str_starts_with($d, '+91')) {
        $d = substr($d, 3);
    } elseif (str_starts_with($d, '0091')) {
        $d = substr($d, 4);
    } elseif (strlen($d) === 12 && str_starts_with($d, '91')) {
        $d = substr($d, 2);
    } elseif (strlen($d) === 11 && str_starts_with($d, '0')) {
        $d = substr($d, 1);
    }
    return preg_match('/^[6-9]\d{9}$/', $d) === 1 ? '+91' . $d : null;
}

/** "+919876543210" -> "+91 98765 43210" */
function jg_phone_display(string $e164): string
{
    $d = substr($e164, 3);
    return '+91 ' . substr($d, 0, 5) . ' ' . substr($d, 5);
}

function jg_count_links(string $s): int
{
    return (int) preg_match_all('~https?://|www\.~i', $s);
}

/**
 * Validates the posted form. Returns [lead, null] or [null, errorMessage].
 * The lead holds only cleaned values.
 */
function jg_validate_lead(array $in): array
{
    $form = is_string($in['form'] ?? null) ? trim($in['form']) : '';
    $forms = jg_forms();
    if (!isset($forms[$form])) {
        return [null, 'Unknown form.'];
    }
    $def = $forms[$form];

    // Header-injection guard: no single-line field may carry CR or LF at all.
    // (Browser text inputs cannot contain line breaks, so real visitors never hit this.)
    foreach (array_merge(['form', 'name', 'phone', 'email', 'page'], array_keys($def['fields'])) as $k) {
        if (isset($in[$k]) && is_string($in[$k]) && preg_match('/[\r\n]/', $in[$k])) {
            return [null, 'The form contains characters we cannot accept.'];
        }
    }

    $name = jg_clean($in['name'] ?? '', 500);
    if (jg_strlen($name) < 2 || jg_count_links($name) > 0) {
        return [null, 'Please enter your name.'];
    }
    if (jg_strlen($name) > 80) {
        return [null, 'Please keep your name under 80 characters.'];
    }

    $phone = jg_normalize_phone($in['phone'] ?? '');
    if ($phone === null) {
        return [null, 'Enter a 10-digit Indian mobile number, like 98XXX XXXXX.'];
    }

    $email = jg_clean($in['email'] ?? '', 254);
    if ($email !== '' && (strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        return [null, "That email doesn't look right. Check it, or leave it empty."];
    }

    $message = jg_clean($in['message'] ?? '', 10000, true);
    if ($def['message_required'] && $message === '') {
        return [null, 'Please enter a message.'];
    }
    if (jg_strlen($message) > 2000) {
        return [null, 'Please keep your message under 2000 characters.'];
    }
    if (jg_count_links($message) > JG_MAX_LINKS) {
        return [null, 'Please remove some of the links from your message.'];
    }

    $fields = [];
    foreach ($def['fields'] as $k => [$label, $max, $required]) {
        $v = jg_clean($in[$k] ?? '', $max);
        if ($k === 'gst') {
            $v = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v) ?? ''), 0, 15);
        }
        if ($required && $v === '') {
            return [null, 'Please enter ' . strtolower($label) . '.'];
        }
        $fields[$k] = $v;
    }

    return [[
        'form' => $form,
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'fields' => $fields,
        'message' => $message,
        'page' => jg_clean($in['page'] ?? '', 300),
    ], null];
}

// ---------------------------------------------------------------------------
// Private storage
// ---------------------------------------------------------------------------

function jg_ensure_data_dir(): bool
{
    $dir = JG_DATA_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        error_log('[jg-api] cannot create data dir');
        return false;
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
    return true;
}

function jg_append_jsonl(string $file, array $record): bool
{
    if (!jg_ensure_data_dir()) {
        return false;
    }
    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($line === false) {
        return false;
    }
    $ok = @file_put_contents(JG_DATA_DIR . '/' . $file, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
    if (!$ok) {
        error_log('[jg-api] cannot write ' . $file);
    }
    return $ok;
}

/**
 * Records one submission for this IP. Returns 0 if allowed, otherwise the seconds to wait.
 * Fails open (allows) if the filesystem misbehaves, so a disk problem never loses a lead.
 */
function jg_rate_limit(string $ip): int
{
    if (!jg_ensure_data_dir()) {
        return 0;
    }
    $dir = JG_DATA_DIR . '/ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return 0;
    }
    $fh = @fopen($dir . '/' . hash('sha256', 'jg-rl|' . $ip) . '.json', 'c+');
    if ($fh === false) {
        return 0;
    }
    $wait = 0;
    if (flock($fh, LOCK_EX)) {
        $now = time();
        $hits = json_decode((string) stream_get_contents($fh), true);
        $hits = is_array($hits) ? $hits : [];
        $hits = array_values(array_filter($hits, static fn($t) => is_int($t) && $t > $now - JG_RATE_LIMIT_WINDOW));
        if (count($hits) >= JG_RATE_LIMIT_MAX) {
            $wait = max(1, $hits[0] + JG_RATE_LIMIT_WINDOW - $now);
        } else {
            $hits[] = $now;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) json_encode($hits));
            fflush($fh);
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);

    // Occasionally sweep files for IPs not seen for a day.
    if (random_int(1, 50) === 1) {
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            if (@filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
    return $wait;
}

// ---------------------------------------------------------------------------
// Templates
// ---------------------------------------------------------------------------

function jg_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Renders templates/{name}.html. {{key}} is HTML-escaped; {{{key}}} is inserted raw and is
 * only ever used for fragments this file builds (names ending in _html).
 */
function jg_render(string $name, array $vars): string
{
    $tpl = @file_get_contents(JG_TEMPLATE_DIR . '/' . basename($name) . '.html');
    if ($tpl === false) {
        throw new RuntimeException('Template missing: ' . $name);
    }
    return preg_replace_callback(
        '/\{\{\{\s*([a-z0-9_]+)\s*\}\}\}|\{\{\s*([a-z0-9_]+)\s*\}\}/i',
        static function (array $m) use ($vars): string {
            if (isset($m[2]) && $m[2] !== '') {
                return jg_e((string) ($vars[$m[2]] ?? ''));
            }
            return (string) ($vars[$m[1]] ?? '');
        },
        $tpl
    ) ?? '';
}

function jg_button(string $href, string $label, string $bg, string $fg): string
{
    return jg_render('_button', ['href' => $href, 'label' => $label, 'bg' => $bg, 'fg' => $fg]);
}

/** @param array<int, array{0:string,1:string}> $rows */
function jg_rows_html(array $rows): string
{
    $out = '';
    foreach ($rows as [$label, $value]) {
        $out .= jg_render('_row', [
            'label' => $label,
            'value_html' => $value === ''
                ? '<span style="color:#9a9da4;font-weight:400;">&mdash;</span>'
                : nl2br(jg_e($value), false),
        ]);
    }
    return $out;
}

function jg_base_vars(): array
{
    return [
        'shop_name' => JG_SHOP_NAME,
        'brand_line' => JG_BRAND_LINE,
        'logo_url' => JG_LOGO_URL,
        'site_url' => JG_SITE_URL,
        'site_host' => (string) parse_url(JG_SITE_URL, PHP_URL_HOST),
        'shop_phone' => JG_SHOP_PHONE,
        'shop_tel_href' => 'tel:' . JG_SHOP_TEL,
        'whatsapp_url' => JG_WHATSAPP_URL,
        'maps_url' => JG_MAPS_URL,
        'instagram_url' => JG_INSTAGRAM_URL,
        'address' => JG_ADDRESS,
        'hours' => JG_HOURS,
    ];
}

function jg_first_name(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [''];
    return $parts[0] !== '' ? $parts[0] : $name;
}

// ---------------------------------------------------------------------------
// Emails
// ---------------------------------------------------------------------------

/**
 * Notification to the shop.
 * $meta: id, time_ist, ip.  Returns [subject, html, text].
 */
function jg_build_shop_email(array $lead, array $meta): array
{
    $def = jg_forms()[$lead['form']];
    $f = $lead['fields'];
    $name = $lead['name'];
    $first = jg_first_name($name);
    $phoneDisplay = jg_phone_display($lead['phone']);
    $email = $lead['email'];

    $service = $f['service'] ?? '';
    $topic = match ($lead['form']) {
        'contact' => $service !== '' ? $service : 'Contact form',
        'partnership' => 'Partnership · ' . $f['company'],
        'vendor' => 'Vendor · ' . $f['company'],
    };
    $subject = 'New enquiry: ' . $name . ' · ' . $topic;

    $waText = 'Hi ' . $first . ', this is ' . JG_SHOP_NAME . ', Loni Kalbhor. ' . match ($lead['form']) {
        'contact' => 'Thanks for your enquiry' . ($service !== '' && $service !== 'Other' ? ' about ' . $service : '') . '.',
        'partnership' => 'Thanks for your partnership enquiry.',
        'vendor' => 'Thanks for sharing your product details.',
    };
    $waUrl = 'https://wa.me/' . ltrim($lead['phone'], '+') . '?text=' . rawurlencode($waText);
    $telHref = 'tel:' . $lead['phone'];

    $rows = [['Name', $name], ['Phone', $phoneDisplay], ['Email', $email]];
    foreach ($def['fields'] as $k => [$label]) {
        $rows[] = [$label, $f[$k]];
    }

    $page = $lead['page'];
    if ($page !== '' && str_starts_with($page, '/')) {
        $page = rtrim(JG_SITE_URL, '/') . $page;
    }
    $metaRows = [
        ['Received', $meta['time_ist']],
        ['Source page', $page],
        ['Form', $def['label']],
        ['Visitor IP', $meta['ip']],
        ['Reference', $meta['id']],
    ];

    $buttons = jg_button($waUrl, 'Reply on WhatsApp', '#25d366', '#121317')
        . jg_button($telHref, 'Call ' . $phoneDisplay, '#ffe500', '#121317');
    if ($email !== '') {
        $buttons .= jg_button('mailto:' . $email, 'Email ' . $first, '#eceef1', '#121317');
    }

    $messageHtml = $lead['message'] === '' ? '' : jg_render('_message', [
        'heading' => 'Message',
        'body_html' => nl2br(jg_e($lead['message']), false),
    ]);

    $replyHint = $email !== ''
        ? 'Reply to this email to answer ' . $first . ' directly, or use the buttons above.'
        : 'No email given. Reply on WhatsApp or call using the buttons above.';

    $html = jg_render($def['template'], jg_base_vars() + [
        'subject' => $subject,
        'preheader' => $name . ' · ' . $phoneDisplay . ' · ' . $topic,
        'badge' => $def['label'] . ' form',
        'title' => $def['title'],
        'name' => $name,
        'phone_display' => $phoneDisplay,
        'buttons_html' => $buttons,
        'rows_html' => jg_rows_html($rows),
        'message_html' => $messageHtml,
        'meta_html' => jg_rows_html($metaRows),
        'reply_hint' => $replyHint,
    ]);

    $t = [$def['title'], str_repeat('=', strlen($def['title'])), ''];
    foreach ($rows as [$l, $v]) {
        $t[] = $l . ': ' . ($v === '' ? '-' : $v);
    }
    if ($lead['message'] !== '') {
        $t[] = '';
        $t[] = 'Message:';
        $t[] = $lead['message'];
    }
    $t[] = '';
    $t[] = 'Reply on WhatsApp: ' . $waUrl;
    $t[] = 'Call: ' . $phoneDisplay;
    $t[] = '';
    foreach ($metaRows as [$l, $v]) {
        $t[] = $l . ': ' . ($v === '' ? '-' : $v);
    }
    $t[] = '';
    $t[] = $replyHint;

    return [$subject, $html, implode("\n", $t) . "\n"];
}

/**
 * Thank-you email to the customer. Deliberately repeats almost nothing the visitor typed
 * (only a plain first name, their number and a known service name), so the form cannot be
 * used to send arbitrary text to arbitrary addresses.
 */
function jg_build_auto_reply(array $lead): array
{
    $first = jg_first_name($lead['name']);
    if (preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\'.-]{0,29}$/u', $first) !== 1) {
        $first = 'there';
    }
    $phoneDisplay = jg_phone_display($lead['phone']);
    $service = $lead['fields']['service'] ?? '';

    $received = match ($lead['form']) {
        'contact' => in_array($service, JG_SERVICE_OPTIONS, true)
            ? "We've received your enquiry about " . $service . '.'
            : "We've received your enquiry.",
        'partnership' => "We've received your partnership enquiry.",
        'vendor' => "We've received your product details.",
    };
    $expect = $lead['form'] === 'vendor'
        ? "If it's a fit for what our customers ask for, we'll get back to you on WhatsApp or by phone within working hours."
        : "We'll reply on WhatsApp or call you on " . $phoneDisplay . ' within working hours.';
    // Keep the number on one line in the HTML version.
    $expectHtml = str_replace($phoneDisplay, str_replace(' ', "\u{00A0}", $phoneDisplay), $expect);

    $subject = 'Thanks for contacting ' . JG_SHOP_NAME;
    $waUrl = JG_WHATSAPP_URL . '?text=' . rawurlencode('Hi Jay Ganesh, I just sent an enquiry on your website.');

    $buttons = jg_button($waUrl, 'WhatsApp us', '#25d366', '#121317')
        . jg_button('tel:' . JG_SHOP_TEL, 'Call ' . JG_SHOP_PHONE, '#ffe500', '#121317');

    $html = jg_render('auto-reply', jg_base_vars() + [
        'subject' => $subject,
        'preheader' => "Thanks, $first. $received",
        'first_name' => $first,
        'received_line' => $received,
        'expect_line' => $expectHtml,
        'buttons_html' => $buttons,
    ]);

    $text = implode("\n", [
        "Hi $first,",
        '',
        "Thanks for contacting " . JG_SHOP_NAME . ". $received",
        $expect,
        '',
        'Working hours: ' . JG_HOURS,
        '',
        'Need us sooner?',
        'WhatsApp: ' . JG_WHATSAPP_URL,
        'Call: ' . JG_SHOP_PHONE,
        '',
        'Visit us: ' . JG_ADDRESS,
        'Directions: ' . JG_MAPS_URL,
        'Instagram: ' . JG_INSTAGRAM_URL,
        '',
        '- ' . JG_SHOP_NAME . ', Loni Kalbhor',
        JG_SITE_URL,
        '',
        'You received this because you sent an enquiry on ' . parse_url(JG_SITE_URL, PHP_URL_HOST) . '. If that was not you, please ignore this email.',
    ]) . "\n";

    return [$subject, $html, $text];
}

// ---------------------------------------------------------------------------
// Mail transport
// ---------------------------------------------------------------------------

/** RFC 2047 "B" encoding for header text; ASCII-only text is returned as is. */
function jg_encode_header(string $s): string
{
    $s = trim(preg_replace('/[\r\n\t]+/', ' ', $s) ?? '');
    if (preg_match('/[^\x20-\x7E]/', $s) !== 1) {
        return $s;
    }
    $words = [];
    $buf = '';
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
        if ($buf !== '' && strlen($buf) + strlen($ch) > 45) {
            $words[] = $buf;
            $buf = '';
        }
        $buf .= $ch;
    }
    if ($buf !== '') {
        $words[] = $buf;
    }
    // Encoded words separated by a space; the space between them is ignored by mail clients.
    return implode(' ', array_map(static fn($w) => '=?UTF-8?B?' . base64_encode($w) . '?=', $words));
}

/** "Name <a@b.c>" -> header-safe mailbox. */
function jg_format_mailbox(string $mailbox): string
{
    if (preg_match('/^\s*(.*?)\s*<([^<>\s]+@[^<>\s]+)>\s*$/', $mailbox, $m) === 1) {
        $name = trim($m[1], " \"");
        if ($name === '') {
            return $m[2];
        }
        $name = preg_match('/[^\x20-\x7E]/', $name) === 1
            ? jg_encode_header($name)
            : '"' . addcslashes($name, "\"\\") . '"';
        return $name . ' <' . $m[2] . '>';
    }
    return trim($mailbox);
}

function jg_qp(string $s): string
{
    // Normalise to CRLF first: quoted_printable_encode keeps CRLF as hard line breaks.
    return quoted_printable_encode(preg_replace("/\r\n|\r|\n/", "\r\n", $s) ?? '');
}

/**
 * Sends a multipart/alternative (text + HTML) UTF-8 message through mail().
 * $extra: header name => value. Any CR/LF in any header value aborts the send.
 */
function jg_send_mail(string $to, string $subject, string $html, string $text, array $extra = []): bool
{
    $boundary = '=_jg_' . bin2hex(random_bytes(12));
    $headers = [
        'From' => jg_format_mailbox(JG_FROM),
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
    ] + $extra;

    foreach (array_merge([$to, $subject], array_keys($headers), array_values($headers)) as $v) {
        if (preg_match('/[\r\n\0]/', (string) $v) === 1) {
            error_log('[jg-api] header injection blocked');
            return false;
        }
    }

    $body = "This is a multi-part message in MIME format.\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
        . jg_qp($text) . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
        . jg_qp($html) . "\r\n\r\n"
        . '--' . $boundary . "--\r\n";

    $ok = @mail($to, jg_encode_header($subject), $body, $headers, '-f' . JG_ENVELOPE_FROM);
    if (!$ok) {
        $err = error_get_last();
        error_log('[jg-api] mail() failed' . ($err ? ': ' . $err['message'] : ''));
    }
    return $ok;
}
