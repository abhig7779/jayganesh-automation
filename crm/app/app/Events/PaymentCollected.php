<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an old due is collected (UX-FLOWS §3.3). WhatsApp can listen to send the
 * receipt ("Received ₹2,000. Balance ₹1,500."); $balancePaise is the customer's due left.
 */
class PaymentCollected implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment, public int $balancePaise) {}
}
