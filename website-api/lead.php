<?php
/**
 * POST /lead.php : receives the website's contact, partnership and vendor forms.
 *
 * Request:  application/x-www-form-urlencoded (multipart/form-data also accepted)
 * Response: {"ok":true} or {"ok":false,"error":"..."} with 400 / 403 / 405 / 429 / 500.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
define('JG_API', true);
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Vary: Origin');

set_exception_handler(static function (Throwable $e): void {
    error_log('[jg-api] ' . get_class($e) . ': ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo '{"ok":false,"error":"Something went wrong on our side. Please call or WhatsApp us."}';
});

// --- Origin / CORS ---------------------------------------------------------
[$allowedOrigin, $viaOriginHeader] = jg_check_origin();
if ($allowedOrigin !== null && $viaOriginHeader) {
    // Echo the exact origin so the browser lets the page read our JSON, errors included.
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'OPTIONS') {
    if ($allowedOrigin === null) {
        jg_json(403, ['ok' => false, 'error' => 'Origin not allowed.']);
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    jg_json(405, ['ok' => false, 'error' => 'Method not allowed.'], ['Allow: POST, OPTIONS']);
}

if ($allowedOrigin === null) {
    jg_json(403, ['ok' => false, 'error' => 'Origin not allowed.']);
}

// --- Request shape ----------------------------------------------------------
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
if (!str_starts_with($contentType, 'application/x-www-form-urlencoded') && !str_starts_with($contentType, 'multipart/form-data')) {
    jg_json(400, ['ok' => false, 'error' => 'Unsupported content type.']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > JG_MAX_BODY_BYTES) {
    jg_json(400, ['ok' => false, 'error' => 'The form is too large.']);
}
if (!empty($_FILES)) {
    jg_json(400, ['ok' => false, 'error' => 'File uploads are not accepted.']);
}
$in = $_POST;

// --- Spam checks ------------------------------------------------------------
// Honeypot: humans never see this field. Pretend success so bots learn nothing.
$hp = $in['website_url'] ?? '';
if (!is_string($hp) || trim($hp) !== '') {
    jg_json(200, ['ok' => true]);
}

// Time on page, in ms since the form rendered.
$t = $in['t'] ?? '';
if (!is_string($t) || preg_match('/^\d{1,12}$/', $t) !== 1 || (int) $t < JG_MIN_FILL_MS) {
    jg_json(400, ['ok' => false, 'error' => 'That was very quick. Please wait a few seconds and send it again.']);
}

// --- Validation -------------------------------------------------------------
[$lead, $error] = jg_validate_lead($in);
if ($lead === null) {
    jg_json(400, ['ok' => false, 'error' => $error]);
}

// --- Rate limit (counts accepted submissions only) ---------------------------
$ip = jg_client_ip();
$wait = jg_rate_limit($ip);
if ($wait > 0) {
    jg_json(429, [
        'ok' => false,
        'error' => 'You have sent several messages already. Please try again in a few minutes, or WhatsApp us on ' . JG_SHOP_PHONE . '.',
    ], ['Retry-After: ' . $wait]);
}

// --- Backup first, so a mail failure never loses the lead ----------------------
$now = new DateTimeImmutable('now', new DateTimeZone(JG_TIMEZONE));
$id = $now->format('Ymd-His') . '-' . bin2hex(random_bytes(3));
$meta = [
    'id' => $id,
    'time_ist' => $now->format('D, j M Y, g:i A') . ' IST',
    'ip' => $ip,
];

jg_append_jsonl('leads.jsonl', [
    'id' => $id,
    'time' => $now->format(DATE_ATOM),
    'form' => $lead['form'],
    'name' => $lead['name'],
    'phone' => $lead['phone'],
    'email' => $lead['email'],
    'fields' => $lead['fields'],
    'message' => $lead['message'],
    'page' => $lead['page'],
    'ip' => $ip,
    'ua' => jg_clean($_SERVER['HTTP_USER_AGENT'] ?? '', 200),
    'origin' => $allowedOrigin,
]);

// --- 1. Notification to the shop -------------------------------------------
[$subject, $html, $text] = jg_build_shop_email($lead, $meta);
$shopHeaders = [];
if ($lead['email'] !== '') {
    $shopHeaders['Reply-To'] = $lead['email'];
}
$sent = jg_send_mail(JG_TO, $subject, $html, $text, $shopHeaders);

if (!$sent) {
    jg_append_jsonl('mail-failures.jsonl', ['id' => $id, 'time' => $now->format(DATE_ATOM), 'to' => 'shop']);
    jg_json(500, [
        'ok' => false,
        'error' => 'We could not send your message just now. Please call or WhatsApp us on ' . JG_SHOP_PHONE . '.',
    ]);
}

// --- 2. Thank-you to the customer (best effort) -----------------------------
if (JG_SEND_AUTO_REPLY && $lead['email'] !== '') {
    try {
        [$subject, $html, $text] = jg_build_auto_reply($lead);
        $ok = jg_send_mail($lead['email'], $subject, $html, $text, [
            'Reply-To' => JG_REPLY_TO_SHOP,
            'Auto-Submitted' => 'auto-replied',
            'X-Auto-Response-Suppress' => 'All',
        ]);
        if (!$ok) {
            jg_append_jsonl('mail-failures.jsonl', ['id' => $id, 'time' => $now->format(DATE_ATOM), 'to' => 'customer']);
        }
    } catch (Throwable $e) {
        error_log('[jg-api] auto-reply: ' . $e->getMessage());
    }
}

jg_json(200, ['ok' => true]);
