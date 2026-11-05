<?php

// Lead intake: the website form (POST /api/v1/leads) and Meta lead ads (crm/LEADS-API.md).
return [
    // The same mobile within this many days is added to the open lead instead of a new one.
    'dedupe_days' => 7,

    // POST /api/v1/leads limits: per caller IP per minute, and per API key per hour.
    'api_per_minute_per_ip' => (int) env('LEADS_API_PER_MINUTE', 20),
    'api_per_hour_per_key' => (int) env('LEADS_API_PER_HOUR', 300),

    'meta' => [
        // Off until the Facebook page is connected. When off, /webhooks/meta-leads answers 404.
        'enabled' => (bool) env('META_LEADS_ENABLED', false),
        // App secret of the Meta app that sends the webhook. Blank: the WhatsApp app's secret.
        'app_secret' => env('META_LEADS_APP_SECRET') ?: env('WHATSAPP_APP_SECRET', ''),
        // Any long random text; typed into the Meta app's webhook settings as the "Verify token".
        'verify_token' => env('META_LEADS_VERIFY_TOKEN', ''),
        // Page access token (System User) with leads_retrieval + pages_manage_metadata.
        'page_token' => env('META_LEADS_PAGE_TOKEN', ''),
        'graph_version' => env('META_GRAPH_VERSION', env('WHATSAPP_GRAPH_VERSION', 'v23.0')),
    ],
];
