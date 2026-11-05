<?php

namespace App\Jobs;

use App\Models\OfferRecipient;
use App\Services\Offers\OfferService;
use App\Services\Offers\WhatsAppBridge;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Sends one automatic (paid) offer message through the WhatsApp module. */
class SendOfferMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Retry at 1, 5 and 15 minutes, like the e-bill (UX-FLOWS §4). */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $recipientId) {}

    public function handle(WhatsAppBridge $whatsapp): void
    {
        $r = OfferRecipient::with(['offer', 'customer.cars'])->find($this->recipientId);
        if (! $r || $r->status !== 'queued') {
            return;
        }

        // The customer may have opted out after the offer was queued: never send then.
        if (! $r->customer->whatsapp_opt_in || $r->customer->opted_out_at) {
            $r->forceFill(['status' => 'skipped', 'error' => 'Opted out before sending', 'done_at' => now()])->save();
            $r->offer->refreshCounts();

            return;
        }

        $logId = $whatsapp->sendTemplate(
            $r->mobile,
            (string) $r->offer->template_name,
            OfferService::templateParams($r->offer, $r->customer),
            $r->offer,
        );

        $r->forceFill(['status' => 'sent', 'message_log_id' => $logId, 'error' => null, 'done_at' => now()])->save();
        $r->offer->refreshCounts();
    }

    public function failed(?Throwable $e): void
    {
        $r = OfferRecipient::with('offer')->find($this->recipientId);
        if ($r) {
            $r->forceFill(['status' => 'failed', 'error' => mb_substr((string) $e?->getMessage(), 0, 250), 'done_at' => now()])->save();
            $r->offer->refreshCounts();
        }
    }
}
