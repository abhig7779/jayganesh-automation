<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Graph API (WhatsApp Cloud API). Timeouts on every call; retries only on
 * 5xx and connection errors (a 4xx is our mistake or Meta's decision and will not change by
 * retrying). Errors come back as WhatsAppException with Meta's code. The token is only ever
 * sent in the Authorization header and is never logged or put in an exception.
 */
class CloudApiClient
{
    public function isConfigured(): bool
    {
        return filled($this->token()) && filled($this->phoneNumberId());
    }

    /** .env first; otherwise the number connected from the "Connect WhatsApp" page. */
    private function token(): ?string
    {
        return config('whatsapp.token') ?: WhatsAppConnection::current()?->token;
    }

    public function phoneNumberId(): ?string
    {
        return config('whatsapp.phone_number_id') ?: WhatsAppConnection::current()?->phone_number_id;
    }

    /** Free-form text, allowed inside the customer-service window (24 h, 72 h after an ad click). */
    public function sendText(string $toE164, string $body, ?string $replyTo = null): string
    {
        return $this->sendMessage(array_filter([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => preg_replace('/\D+/', '', $toE164),
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
            'context' => $replyTo ? ['message_id' => $replyTo] : null,
        ]));
    }

    /** Upload a file to /{phone-number-id}/media and return the media id. */
    public function uploadMedia(string $path, string $filename, string $mime = 'application/pdf'): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new WhatsAppException('no_document');
        }

        $response = $this->call(fn () => $this->http()
            ->attach('file', file_get_contents($path), $filename, ['Content-Type' => $mime])
            ->post($this->phonePath('media'), ['messaging_product' => 'whatsapp', 'type' => $mime]));

        return (string) $response->json('id');
    }

    /** POST /{phone-number-id}/messages; returns the wamid. */
    public function sendMessage(array $payload): string
    {
        $response = $this->call(fn () => $this->http()->asJson()->post($this->phonePath('messages'), $payload));

        $id = $response->json('messages.0.id');
        if (! $id) {
            throw new WhatsAppException('server', 'No message id in the response');
        }

        return (string) $id;
    }

    public function markRead(string $waMessageId): void
    {
        $this->call(fn () => $this->http()->asJson()->post($this->phonePath('messages'), [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $waMessageId,
        ]));
    }

    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new WhatsAppException('not_configured');
        }

        return Http::baseUrl(rtrim(config('whatsapp.graph_url'), '/').'/'.config('whatsapp.graph_version'))
            ->withToken((string) $this->token())
            ->acceptJson()
            ->timeout((int) config('whatsapp.http.timeout', 20))
            ->connectTimeout((int) config('whatsapp.http.connect_timeout', 5))
            ->retry(
                (int) config('whatsapp.http.retries', 3),
                (int) config('whatsapp.http.retry_sleep_ms', 400),
                fn (\Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
    }

    private function phonePath(string $edge): string
    {
        return '/'.$this->phoneNumberId().'/'.$edge;
    }

    /** Run a request and turn every failure into a WhatsAppException with Meta's error code. */
    private function call(callable $send): Response
    {
        try {
            /** @var Response $response */
            $response = $send();
        } catch (ConnectionException) {
            throw new WhatsAppException('network');
        }

        if ($response->successful()) {
            return $response;
        }

        $code = $response->json('error.code');
        $details = $response->json('error.error_data.details') ?? $response->json('error.message');
        if ($code === null) {
            $code = $response->serverError() ? 'server' : (string) $response->status();
        }

        throw new WhatsAppException((string) $code, is_string($details) ? $details : null, $response->status());
    }
}
