<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

class ErrorDigest extends Mailable
{
    use Queueable;

    /** @param list<array{level: string, message: string, count: int, last: string}> $groups */
    public function __construct(public array $groups, public Carbon $since) {}

    public function envelope(): Envelope
    {
        $total = array_sum(array_column($this->groups, 'count'));

        return new Envelope(subject: "Jay Ganesh CRM: {$total} error(s) in the last 24 hours");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.error-digest');
    }
}
