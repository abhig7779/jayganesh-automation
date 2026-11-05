<?php

namespace App\Jobs;

use App\Models\MessageLog;
use App\Services\WhatsApp\ErrorMap;
use App\Services\WhatsApp\WhatsApp;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one queued Cloud API message_log row (ARCHITECTURE.md §2).
 *
 * Normal path: dispatched with ->afterResponse(), so it runs in the same PHP process right after
 * the bill page is sent. If that first try fails with something worth retrying, it puts itself
 * on the database queue: tries again at about 1, 5 and 15 minutes, then marks the log failed.
 * Failures that retrying cannot fix (number not on WhatsApp, template missing, bad token) are
 * marked failed at once so the Resend / Send manually buttons show without waiting 20 minutes.
 */
class SendWhatsAppTemplate implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /**
     * @param  bool  $firstTryDone  true when the in-process (after-response) try already failed
     */
    public function __construct(public int $messageLogId, public bool $firstTryDone = false)
    {
        // 4 tries in all either way: now, +1 min, +5 min, +15 min.
        $this->tries = $firstTryDone ? 3 : 4;
        $this->onConnection(config('whatsapp.queue_connection', 'database'));
    }

    /** Seconds to wait before each retry. */
    public function backoff(): array
    {
        return $this->firstTryDone ? [300, 900] : [60, 300, 900];
    }

    public function handle(WhatsApp $whatsApp): void
    {
        $log = MessageLog::find($this->messageLogId);
        if (! $log || $log->status !== 'queued' || $log->isManual()) {
            return; // already sent, failed for good, or switched to manual
        }

        try {
            $whatsApp->deliver($log);
        } catch (WhatsAppException $e) {
            if (! $e->retryable()) {
                $whatsApp->markFailed($log, $e->errorCode, $e->staffMessage());

                return;
            }

            if ($this->runningInline()) {
                // The after-response try failed: hand over to the database queue (+1 min).
                static::dispatch($log->id, true)->delay(now()->addSeconds(60));

                return;
            }

            if ($this->attempts() >= $this->tries) {
                $whatsApp->markFailed($log, $e->errorCode, $e->staffMessage());

                return;
            }

            throw $e; // the queue retries after backoff()
        }
    }

    /** Called by the queue after the last try, or for an unexpected error. */
    public function failed(?Throwable $e): void
    {
        $log = MessageLog::find($this->messageLogId);
        if (! $log || $log->isSent()) {
            return;
        }

        $code = $e instanceof WhatsAppException ? $e->errorCode : 'server';
        $text = $e instanceof WhatsAppException ? $e->staffMessage() : ErrorMap::message('server');
        app(WhatsApp::class)->markFailed($log, $code, $text);
    }

    private function runningInline(): bool
    {
        return $this->job === null || $this->job->getConnectionName() === 'sync';
    }
}
