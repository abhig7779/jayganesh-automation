<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppTemplate;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;

/**
 * The one door to WhatsApp for every module (crm/CONTRACTS.md §4).
 *
 * - Manual mode (default until the number is on the Cloud API): nothing is sent by us. A log row
 *   is kept with channel=manual, status=queued, and the UI opens manualLink() for staff to send.
 * - Cloud API mode: template (+ PDF header) sent through Meta, wa_message_id stored, statuses
 *   arrive later by webhook.
 *
 * Opt-out rules: marketing never goes to a customer who opted out. Utility and service messages
 * also respect it while Settings `whatsapp.utility_respects_opt_out` is on (the default).
 */
class WhatsApp
{
    /** Settings key (whatsapp.<key>) → category, so renamed templates keep their category. */
    private const TEMPLATE_SETTINGS = [
        'template_invoice' => 'utility',
        'template_payment_receipt' => 'utility',
        'template_quotation' => 'utility',
        'template_vehicle_ready' => 'utility',
        'template_followup' => 'marketing',
        'template_review' => 'marketing',
    ];

    public function __construct(private CloudApiClient $client) {}

    public function isAutomatic(): bool
    {
        return Setting::get('whatsapp.mode') === 'cloud_api';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /** https://wa.me/919823107676?text=Hi%20Rahul… (opens WhatsApp with the message typed in). */
    public function manualLink(string $toE164, string $text): string
    {
        $digits = preg_replace('/\D+/', '', (string) Phone::normalize($toE164));

        return 'https://wa.me/'.$digits.($text !== '' ? '?text='.rawurlencode($text) : '');
    }

    /**
     * Send a template now (Cloud API) or prepare it for manual sending. Never throws for a
     * WhatsApp failure: the returned log says failed, with the plain-English reason.
     *
     * $options: category (utility|marketing, else worked out from the name), language,
     * document_pending (bill PDF still to render; the job does it).
     */
    public function sendTemplate(
        string $toE164,
        string $template,
        array $bodyParams,
        ?string $documentPath = null,
        ?string $documentName = null,
        ?Model $related = null,
        array $options = [],
    ): MessageLog {
        $log = $this->prepareTemplate($toE164, $template, $bodyParams, $documentPath, $documentName, $related, $options);

        if ($log->status === 'queued' && ! $log->isManual()) {
            try {
                $this->deliver($log);
            } catch (WhatsAppException $e) {
                $this->markFailed($log, $e->errorCode, $e->staffMessage());
            }
        }

        return $log;
    }

    /**
     * Same as sendTemplate, but the Cloud API send happens after the HTTP response (instant
     * e-bill), with the database queue as the fallback (1, 5, 15 min). Manual mode: just the log.
     */
    public function queueTemplate(
        string $toE164,
        string $template,
        array $bodyParams,
        ?string $documentPath = null,
        ?string $documentName = null,
        ?Model $related = null,
        array $options = [],
    ): MessageLog {
        $log = $this->prepareTemplate($toE164, $template, $bodyParams, $documentPath, $documentName, $related, $options);

        if ($log->status === 'queued' && ! $log->isManual()) {
            SendWhatsAppTemplate::dispatch($log->id)->afterResponse();
        }

        return $log;
    }

    /** Free text, only valid inside the 24 h window after the customer messaged us. */
    public function sendText(string $toE164, string $text, ?Model $related = null): MessageLog
    {
        $log = $this->newLog($toE164, null, 'service', $related, ['text' => $text]);

        if ($log->status === 'queued' && ! $log->isManual()) {
            try {
                $log->attempts++;
                $log->wa_message_id = $this->client->sendMessage([
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $this->digits($log->to),
                    'type' => 'text',
                    'text' => ['preview_url' => false, 'body' => $text],
                ]);
                $log->status = 'sent';
                $log->sent_at = now();
                $log->save();
            } catch (WhatsAppException $e) {
                $this->markFailed($log, $e->errorCode, $e->staffMessage());
            }
        }

        return $log;
    }

    /**
     * Send a queued Cloud API log: upload the PDF (if any), then the template. Throws
     * WhatsAppException on failure after noting the reason on the log (status stays queued so
     * the caller decides between retry and failed).
     */
    public function deliver(MessageLog $log): MessageLog
    {
        $log->attempts++;
        $log->save();

        try {
            $components = [];

            if ($log->bodyValue('document_pending') || $log->bodyValue('document_path')) {
                $mediaId = $log->bodyValue('media_id');
                if (! $mediaId) {
                    $path = $log->bodyValue('document_path') ?: app(DocumentResolver::class)->resolve($log);
                    if (! $path) {
                        throw new WhatsAppException('no_document');
                    }
                    $log->mergeBody(['document_path' => $path, 'document_pending' => false])->save();
                    $mediaId = $this->client->uploadMedia($path, $log->document_name ?: basename($path));
                    // Media ids live 30 days at Meta; keep it so a retry does not upload again.
                    $log->mergeBody(['media_id' => $mediaId])->save();
                }
                $components[] = ['type' => 'header', 'parameters' => [[
                    'type' => 'document',
                    'document' => ['id' => $mediaId, 'filename' => $log->document_name ?: 'document.pdf'],
                ]]];
            }

            if ($params = $log->params()) {
                $components[] = ['type' => 'body', 'parameters' => array_map(
                    fn ($p) => ['type' => 'text', 'text' => $p],
                    $params,
                )];
            }

            $template = ['name' => $log->template, 'language' => ['code' => $log->bodyValue('language', 'en')]];
            if ($components) {
                $template['components'] = $components;
            }

            $wamid = $this->client->sendMessage([
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->digits($log->to),
                'type' => 'template',
                'template' => $template,
            ]);
        } catch (WhatsAppException $e) {
            $log->forceFill(['error_code' => $e->errorCode, 'error_text' => $e->staffMessage()])->save();

            throw $e;
        }

        $log->forceFill([
            'wa_message_id' => $wamid,
            'status' => 'sent',
            'sent_at' => $log->sent_at ?? now(),
            'error_code' => null,
            'error_text' => null,
        ])->save();

        return $log;
    }

    public function markFailed(MessageLog $log, string $code, ?string $text = null): MessageLog
    {
        $log->forceFill([
            'status' => 'failed',
            'failed_at' => now(),
            'error_code' => $code,
            'error_text' => $text ?? ErrorMap::message($code),
        ])->save();

        return $log;
    }

    /** Send the same message again as a new log row (the old row stays as history). */
    public function resend(MessageLog $old): MessageLog
    {
        $body = collect($old->body ?? [])->except(['media_id'])->all();
        $options = [
            'category' => $old->category,
            'language' => $body['language'] ?? null,
            'document_pending' => ! empty($body['document_pending']),
            'resend_of' => $old->id,
        ];

        if ($old->template === null) {
            return $this->sendText($old->to, (string) ($body['text'] ?? ''), $old->related);
        }

        return $this->queueTemplate(
            $old->to, $old->template, $old->params(),
            $body['document_path'] ?? null, $old->document_name, $old->related, $options,
        );
    }

    /** Category of a template: by its Settings key first, then config, else marketing. */
    public function categoryFor(string $template): string
    {
        foreach (self::TEMPLATE_SETTINGS as $key => $category) {
            if (Setting::get("whatsapp.$key") === $template) {
                return $category;
            }
        }

        return config("whatsapp.categories.$template", 'marketing');
    }

    /** Why this number must not get a message of this category, or null if it may. */
    public function blockedReason(string $toE164, string $category): ?string
    {
        if (! Phone::isValidIndianMobile($toE164) && ! preg_match('/^\+\d{8,15}$/', $toE164)) {
            return 'no_number';
        }
        if ($category !== 'marketing' && ! Setting::get('whatsapp.utility_respects_opt_out')) {
            return null;
        }

        return $this->isOptedOut($toE164) ? 'opted_out' : null;
    }

    /** The customer record says so, or (for numbers we have no customer for) their last keyword was STOP. */
    public function isOptedOut(string $toE164): bool
    {
        $customer = Customer::query()->where('mobile', $toE164)->first();
        if ($customer) {
            return ! $customer->whatsapp_opt_in || $customer->opted_out_at !== null;
        }

        $last = MessageLog::query()
            ->where('to', $toE164)->where('direction', 'in')
            ->whereIn('body->keyword', ['stop', 'start'])
            ->latest('id')->first();

        return $last?->bodyValue('keyword') === 'stop';
    }

    private function prepareTemplate(
        string $toE164,
        string $template,
        array $bodyParams,
        ?string $documentPath,
        ?string $documentName,
        ?Model $related,
        array $options,
    ): MessageLog {
        $category = $options['category'] ?? $this->categoryFor($template);
        $body = array_filter([
            'params' => array_map(fn ($p) => $this->cleanParam($p), array_values($bodyParams)),
            'language' => $options['language'] ?? Setting::get('whatsapp.template_language') ?? 'en',
            'document_path' => $documentPath,
            'document_pending' => ! $documentPath && ! empty($options['document_pending']) ? true : null,
            'resend_of' => $options['resend_of'] ?? null,
        ], fn ($v) => $v !== null);

        $log = $this->newLog($toE164, $template, $category, $related, $body, $documentName);

        return $log;
    }

    private function newLog(string $toE164, ?string $template, string $category, ?Model $related, array $body, ?string $documentName = null): MessageLog
    {
        $to = (string) Phone::normalize($toE164);
        $customer = $to ? Customer::query()->where('mobile', $to)->first() : null;
        $user = auth()->user();

        $log = MessageLog::create([
            'branch_id' => $user?->branch_id ?? $customer?->branch_id ?? $related?->getAttribute('branch_id') ?? Branch::query()->value('id'),
            'customer_id' => $customer?->id,
            'to' => $to,
            'direction' => 'out',
            'channel' => $this->isAutomatic() ? 'cloud_api' : 'manual',
            'template' => $template,
            'category' => $category,
            'body' => $body,
            'document_name' => $documentName ? mb_substr($documentName, 0, 120) : null,
            'status' => 'queued',
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'created_by' => $user?->id,
        ]);

        if ($reason = $this->blockedReason($to, $category)) {
            $this->markFailed($log, $reason);
        }

        return $log;
    }

    /** Meta rejects empty values, new lines, tabs and 4+ spaces in a template value. */
    private function cleanParam(mixed $value): string
    {
        $value = preg_replace('/\s{4,}/', '   ', str_replace(["\r", "\n", "\t"], ' ', trim((string) $value)));

        return $value === '' ? '-' : mb_substr($value, 0, 1000);
    }

    private function digits(string $e164): string
    {
        return preg_replace('/\D+/', '', $e164);
    }
}
