<?php

namespace App\Services\WhatsApp\Jobs;

use App\Models\MessageLog;
use App\Services\WhatsApp\AiReplyAgent;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Writes and sends one AI reply to an inbound WhatsApp message (see AiReplyAgent). */
class SendAiReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $messageLogId)
    {
        $this->onConnection(config('whatsapp.queue_connection', 'database'));
    }

    public function backoff(): array
    {
        return [30];
    }

    public function handle(AiReplyAgent $agent): void
    {
        $in = MessageLog::query()->find($this->messageLogId);
        if (! $in || $in->direction !== 'in') {
            return;
        }

        try {
            $agent->reply($in);
        } catch (WhatsAppException $e) {
            Log::warning('WhatsApp AI reply not sent', ['log_id' => $in->id, 'code' => $e->errorCode]);
            if ($e->retryable()) {
                throw $e;
            }
        }
    }
}
