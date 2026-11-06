<?php

namespace App\Services\WhatsApp;

/**
 * Meta Cloud API error codes → what staff at the counter should read and do.
 * Codes: https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes
 * Our own codes (not from Meta) are words: network, not_configured, opted_out, no_document, no_number.
 */
final class ErrorMap
{
    private const MESSAGES = [
        // Meta
        '131026' => 'Not delivered: this number may not be on WhatsApp, or the customer has not accepted WhatsApp\'s latest terms. Send it manually or call them.',
        '131047' => 'More than 24 hours since the customer last messaged us, so WhatsApp only allows an approved template. Use a template or send it manually.',
        '132001' => 'The WhatsApp template is missing or not approved yet (check the template name and language in Settings > WhatsApp).',
        '132000' => 'The template got the wrong number of values. Check the template in WhatsApp Manager matches WHATSAPP-TEMPLATES.md.',
        '132012' => 'The template\'s values do not match its format (for example a missing PDF header). Check the template in WhatsApp Manager.',
        '132015' => 'The template is paused by WhatsApp because of low quality. Send manually for now and ask the owner to check WhatsApp Manager.',
        '132016' => 'The template is disabled by WhatsApp. Send manually and ask the owner to submit a new template.',
        '190' => 'The WhatsApp access token has expired or is wrong. The owner must put a new WHATSAPP_TOKEN in the server settings.',
        '10' => 'The WhatsApp token is missing a permission (whatsapp_business_messaging). The owner must fix the System User token.',
        '200' => 'The WhatsApp token is missing a permission (whatsapp_business_messaging). The owner must fix the System User token.',
        '131056' => 'Too many messages to this number in a short time. It will retry automatically.',
        '130429' => 'WhatsApp is limiting how fast we send right now. It will retry automatically.',
        '80007' => 'WhatsApp is limiting how fast we send right now. It will retry automatically.',
        '131049' => 'WhatsApp held back this marketing message to protect the customer\'s experience. Try again in a few days or send manually.',
        '131050' => 'The customer has stopped marketing messages from us in WhatsApp.',
        '131021' => 'This is our own business number. Use the customer\'s number.',
        '131051' => 'This kind of message is not supported by WhatsApp.',
        '131052' => 'The PDF could not be downloaded by WhatsApp. Try again.',
        '131053' => 'The PDF could not be uploaded to WhatsApp (check the file size, max 100 MB). Try again.',
        '131042' => 'There is a payment problem on the WhatsApp account. The owner must check the payment method in Meta Business.',
        '131031' => 'The WhatsApp business account is locked. The owner must check WhatsApp Manager.',
        '133010' => 'The business number is not registered on the WhatsApp Cloud API yet.',
        '131000' => 'WhatsApp had a temporary problem. It will retry automatically.',
        '131016' => 'WhatsApp is temporarily unavailable. It will retry automatically.',
        '1' => 'WhatsApp had a temporary problem. It will retry automatically.',
        '2' => 'WhatsApp is temporarily unavailable. It will retry automatically.',
        '100' => 'WhatsApp rejected the request (invalid value). Check the number and template.',
        '131008' => 'A required value for the template is missing.',
        '131009' => 'One of the values is not allowed by WhatsApp (check the number and template).',

        // Ours
        'network' => 'Could not reach WhatsApp (internet or Meta problem). It will retry automatically.',
        'server' => 'WhatsApp had a temporary problem. It will retry automatically.',
        'not_configured' => 'WhatsApp Cloud API is not set up yet (missing token or phone number id). Send manually.',
        'opted_out' => 'The customer has opted out of WhatsApp messages (they sent STOP), so nothing was sent.',
        'no_document' => 'The bill PDF could not be made, so the e-bill was not sent. Send manually.',
        'no_number' => 'No valid WhatsApp number for this customer.',
        'signup_no_waba' => 'Meta did not send a WhatsApp account id. Start "Connect WhatsApp" again and finish every step.',
        'signup_no_number' => 'No phone number is attached to this WhatsApp account yet. Start "Connect WhatsApp" again and pick the number.',
        'signup_no_token' => 'Meta did not return an access token (the sign-up code expires in 30 seconds). Please try again.',
    ];

    /** Codes worth retrying: rate limits, Meta outages, our network. Everything else needs a person. */
    private const RETRYABLE = ['131056', '130429', '80007', '131000', '131016', '1', '2', '131052', '131053', 'network', 'server'];

    public static function message(int|string|null $code, ?string $fallback = null): string
    {
        $code = (string) $code;
        if (isset(self::MESSAGES[$code])) {
            return self::MESSAGES[$code];
        }

        return $fallback
            ? 'WhatsApp could not send this ('.$code.'): '.mb_strimwidth($fallback, 0, 300, '…')
            : 'WhatsApp could not send this (error '.$code.').';
    }

    public static function retryable(int|string|null $code): bool
    {
        return in_array((string) $code, self::RETRYABLE, true);
    }
}
