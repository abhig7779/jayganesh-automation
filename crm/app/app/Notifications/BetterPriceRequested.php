<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * "Customer wants a better price" → Owner/Admin, to approve a revised quote. Stored in the
 * database; the shell's Owner home can list unread ones (`$user->unreadNotifications`).
 */
class BetterPriceRequested extends Notification
{
    use Queueable;

    public function __construct(public Model $subject, public User $by, public ?string $note = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $s = $this->subject;
        if ($s instanceof Quotation) {
            $s->loadMissing('customer', 'car');

            return [
                'kind' => 'better_price',
                'title' => "{$s->customer->name} wants a better price",
                'body' => trim($s->number.' · '.$s->carLabel().' · '.Money::format($s->total_paise), ' ·'),
                'note' => $this->note,
                'quotation_id' => $s->id,
                'url' => route('quotations.show', $s),
                'by' => $this->by->name,
            ];
        }

        /** @var Lead $s */
        return [
            'kind' => 'better_price',
            'title' => $s->displayName().' wants a better price',
            'body' => trim(($s->interest ?? '').($s->price_told_paise ? ' · told '.Money::format($s->price_told_paise) : '')),
            'note' => $this->note,
            'lead_id' => $s->id,
            'url' => route('leads.index'),
            'by' => $this->by->name,
        ];
    }
}
