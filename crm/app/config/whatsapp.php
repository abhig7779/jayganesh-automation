<?php

/*
| WhatsApp Cloud API (Meta Graph API). Secrets live only in .env, never in Settings or logs.
| The sending mode (manual / cloud_api) is a Setting (`whatsapp.mode`), so the owner can switch it
| from the Settings page once these values are filled. See crm/WHATSAPP-TEMPLATES.md.
*/

return [
    // Permanent System User token with whatsapp_business_messaging (+ whatsapp_business_management).
    'token' => env('WHATSAPP_TOKEN'),

    // WhatsApp Manager → Phone numbers → the number's "Phone number ID" (not the phone number).
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    // WhatsApp Business Account ID (used for template checks and the setup card).
    'waba_id' => env('WHATSAPP_WABA_ID'),

    // Meta app → Settings → Basic → App secret. Signs every webhook (X-Hub-Signature-256).
    'app_secret' => env('WHATSAPP_APP_SECRET'),

    // Any long random string; typed again in the Meta app's webhook setup.
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),

    'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),

    'http' => [
        'timeout' => 20,          // seconds per request (PDF uploads included)
        'connect_timeout' => 5,
        'retries' => 3,           // total tries, only for 5xx / connection errors
        'retry_sleep_ms' => 400,
    ],

    // The queue that holds retries (cron runs `queue:work` every minute on shared hosting).
    'queue_connection' => env('WHATSAPP_QUEUE_CONNECTION', 'database'),

    /*
    | Category of each template by name. Templates renamed in Settings are matched through their
    | Settings key first (see WhatsApp::categoryFor); anything unknown counts as marketing, which
    | is the safe side for opt-outs.
    */
    'categories' => [
        'invoice_ebill' => 'utility',
        'payment_receipt' => 'utility',
        'quotation_send' => 'utility',
        'vehicle_ready' => 'utility',
        'followup_reminder' => 'marketing',
        'review_request' => 'marketing',
        'hello_world' => 'utility',
    ],

    /*
    | The approved template bodies (same text as in WHATSAPP-TEMPLATES.md). Used for manual mode,
    | where staff send the text themselves from WhatsApp, and for the log preview.
    */
    'texts' => [
        'invoice_ebill' => "Hi {{1}}, thank you for visiting Jay Ganesh Car Accessories!\n\nYour invoice {{2}} for {{3}} is attached. Payment status: {{4}}.\n\nFor any help with your purchase, just reply to this message.",
        'payment_receipt' => "Hi {{1}}, we have received your payment of {{2}} against invoice {{3}}. Balance now: {{4}}.\n\nThank you, Jay Ganesh Car Accessories.",
        'quotation_send' => "Hi {{1}}, here is your quotation {{2}} from Jay Ganesh Car Accessories for {{3}}. Total: {{4}}, valid till {{5}}.\n\nReply here if you have any questions.",
        'vehicle_ready' => "Hi {{1}}, your {{2}} is ready for pickup at Jay Ganesh Car Accessories. Job: {{3}}.\n\nWe are open till {{4}} today.",
        'followup_reminder' => 'Hi {{1}}, this is Jay Ganesh Car Accessories. Your quotation for {{2}} is valid till {{3}}. Would you like to book a time to visit?',
        'review_request' => "Hi {{1}}, thank you for choosing Jay Ganesh Car Accessories for your {{2}}. If you liked our work, please leave us a Google review here: {{3}}\n\nThank you!",
        'hello_world' => 'Hello World (Meta sample template).',
    ],
];
