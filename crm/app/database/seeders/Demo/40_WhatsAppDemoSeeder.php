<?php

namespace Database\Seeders\Demo;

use App\Models\Customer;
use App\Models\MessageLog;
use App\Services\WhatsApp\ErrorMap;
use Illuminate\Database\Seeder;

/** A few WhatsApp log rows in every state, so the log screen and ticks can be checked. Local only. */
class WhatsAppDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (MessageLog::query()->exists()) {
            return;
        }

        $customers = Customer::query()->orderBy('id')->take(7)->get();
        $states = ['queued', 'manual_opened', 'sent', 'delivered', 'read', 'failed', 'read'];

        foreach ($customers as $i => $c) {
            $status = $states[$i % count($states)];
            $number = sprintf('JG-2526-%04d', 101 + $i);
            $at = now()->subHours(3 * (count($customers) - $i));
            $manual = in_array($status, ['queued', 'manual_opened'], true);

            MessageLog::create([
                'branch_id' => $c->branch_id,
                'customer_id' => $c->id,
                'to' => $c->mobile,
                'direction' => 'out',
                'channel' => $manual ? 'manual' : 'cloud_api',
                'template' => 'invoice_ebill',
                'category' => 'utility',
                'body' => ['params' => [$c->name, $number, '₹'.number_format(1500 + 750 * $i), 'Paid'], 'language' => 'en'],
                'document_name' => $number.'.pdf',
                'wa_message_id' => $manual ? null : 'wamid.DEMO'.$i,
                'status' => $status,
                'attempts' => $manual ? 0 : 1,
                'error_code' => $status === 'failed' ? '131026' : null,
                'error_text' => $status === 'failed' ? ErrorMap::message('131026') : null,
                'sent_at' => in_array($status, ['sent', 'delivered', 'read', 'manual_opened'], true) ? $at : null,
                'delivered_at' => in_array($status, ['delivered', 'read'], true) ? $at->copy()->addSeconds(4) : null,
                'read_at' => $status === 'read' ? $at->copy()->addMinutes(12) : null,
                'failed_at' => $status === 'failed' ? $at : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }
}
