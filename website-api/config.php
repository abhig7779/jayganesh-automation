<?php
/**
 * Jay Ganesh website API: configuration.
 *
 * Included by lead.php and preview.php. Never served directly: .htaccess denies it,
 * and the guard below returns 404 if it is ever requested on its own.
 * No passwords or API keys live here; mail goes out through the server's own mail().
 */
declare(strict_types=1);

if (!defined('JG_API')) {
    http_response_code(404);
    exit;
}

// --- Mail ------------------------------------------------------------------
// Where new leads are sent (shop inbox).
const JG_TO = 'marutikalbhor760@gmail.com';
// Visible sender. Must be an address on the domain whose SPF covers this server.
const JG_FROM = 'Jay Ganesh Website <noreply@jayganeshcaraccessories.com>';
// Envelope sender (Return-Path), passed to mail() as "-f". Bounces go here.
const JG_ENVELOPE_FROM = 'noreply@jayganeshcaraccessories.com';
// Where customers' replies to the auto-reply go.
const JG_REPLY_TO_SHOP = 'marutikalbhor760@gmail.com';
// Send a thank-you email to customers who give an email address.
const JG_SEND_AUTO_REPLY = true;

// --- Shop facts (used in emails; keep them true) ----------------------------
const JG_SHOP_NAME     = 'Jay Ganesh Car Accessories';
const JG_BRAND_LINE    = 'Car Detailing & Accessories';
const JG_SHOP_PHONE    = '+91 99813 97676';
const JG_SHOP_TEL      = '+919981397676';
const JG_WHATSAPP_URL  = 'https://wa.me/919981397676';
const JG_SITE_URL      = 'https://jayganeshcaraccessories.com';
const JG_LOGO_URL      = 'https://jayganeshcaraccessories.com/logo.png';
const JG_ADDRESS       = 'Kawadipat, Toll Naka, Pune–Solapur Road, Loni Kalbhor, Pune';
const JG_HOURS         = 'Open 7 days · 10 am – 8 pm';
const JG_MAPS_URL      = 'https://www.google.com/maps/search/?api=1&query=Jay+Ganesh+Car+Accessories+Loni+Kalbhor';
const JG_INSTAGRAM_URL = 'https://www.instagram.com/jayganeshcar_accessories_pune/';
const JG_TIMEZONE      = 'Asia/Kolkata';

// Service names offered on the website's contact form (lib/forms.ts serviceOptions).
// Only these are repeated back to the customer in the auto-reply.
const JG_SERVICE_OPTIONS = [
    'PPF',
    'Ceramic / graphene coating',
    'Detailing',
    'Seat covers',
    'Lights',
    'Audio & screens',
    'Cameras & dashcams',
];

// --- Security ---------------------------------------------------------------
// Browser origins allowed to post leads (exact scheme://host[:port], lowercase).
const JG_ALLOWED_ORIGINS = [
    'https://jayganeshcaraccessories.com',
    'https://www.jayganeshcaraccessories.com',
    'https://jayganesh-automation.vercel.app',
    'http://localhost:3100',
];

// Rate limit: at most this many accepted submissions per IP per window.
const JG_RATE_LIMIT_MAX    = 5;
const JG_RATE_LIMIT_WINDOW = 600; // seconds (10 minutes)
// A human needs at least this long between the form rendering and submit.
const JG_MIN_FILL_MS = 3000;
// Messages with more links than this are rejected.
const JG_MAX_LINKS = 3;
// Largest request body accepted, in bytes.
const JG_MAX_BODY_BYTES = 65536;

// Private storage for rate-limit files and the lead backup (leads.jsonl).
// It is denied by .htaccess. To keep it outside the web root entirely, point it
// one level up, e.g. dirname(__DIR__) . '/jg-api-data'.
define('JG_DATA_DIR', __DIR__ . '/data');
