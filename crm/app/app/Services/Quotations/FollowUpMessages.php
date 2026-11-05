<?php

namespace App\Services\Quotations;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Setting;
use App\Support\Money;
use App\Support\Phone;
use App\Support\SettingsSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * The polite English WhatsApp texts for each follow-up step, from editable Settings
 * (`followups.msg_day1` … `msg_day10`, `msg_visit`, `msg_lead`), and the wa.me links.
 * wa.me opens WhatsApp Web / Desktop on the counter PC and the app on a phone.
 */
class FollowUpMessages
{
    /** Message keys in schedule order: the 1st planned day uses msg_day1, the 2nd msg_day3 … */
    public const STEP_KEYS = ['followups.msg_day1', 'followups.msg_day3', 'followups.msg_day6', 'followups.msg_day10'];

    public function forFollowUp(FollowUp $f): string
    {
        $subject = $f->subject;
        if ($subject instanceof Lead) {
            return $this->forLead($subject);
        }
        $key = $f->kind === 'visit' ? 'followups.msg_visit' : self::keyForStep($f->step);

        return $this->forQuotation($subject, $key);
    }

    /** The owner's text from Settings, or the built-in default when the field was left empty. */
    public static function template(string $key, ?int $branchId = null): string
    {
        $text = trim((string) Setting::get($key, null, $branchId));

        return $text !== '' ? $text : (string) (SettingsSchema::fields()[$key]['default'] ?? '');
    }

    public static function keyForStep(int $step): string
    {
        $days = FollowUpScheduler::scheduleDays();
        $pos = 0;
        foreach ($days as $i => $d) {
            if ($step >= $d) {
                $pos = $i;
            }
        }

        return self::STEP_KEYS[min($pos, count(self::STEP_KEYS) - 1)];
    }

    public function forQuotation(Quotation $q, string $key = 'followups.msg_day1'): string
    {
        $q->loadMissing('customer', 'car', 'items');

        return $this->fill(self::template($key, $q->branch_id), [
            'name' => $q->customer?->name,
            'car' => $q->car ? $q->car->model : 'car',
            'items' => $q->itemsSummary(3),
            'amount' => Money::format($q->total_paise),
            'valid_until' => $q->valid_until?->format('j M Y'),
            'pdf_link' => self::pdfLink($q),
            'amount_line' => '',
            'savings' => $q->savings_paise > 0 ? Money::format($q->savings_paise) : '',
            'savings_line' => self::savingsLine($q),
        ]);
    }

    public function forLead(Lead $lead): string
    {
        $lead->loadMissing('customer.cars', 'product');
        $car = $lead->customer?->cars?->first();

        return $this->fill(self::template('followups.msg_lead', $lead->branch_id), [
            'name' => $lead->name ?: $lead->customer?->name,
            'car' => $car ? $car->model : 'car',
            'items' => $lead->interest ?: ($lead->product?->name ?? 'car accessories'),
            'amount' => $lead->price_told_paise ? Money::format($lead->price_told_paise) : '',
            'amount_line' => $lead->price_told_paise ? 'The price is '.Money::format($lead->price_told_paise).' including GST. ' : '',
            'valid_until' => '',
            'savings' => '',
            'savings_line' => '',
            'pdf_link' => '',
        ]);
    }

    /** "MRP ₹38,000 → our price ₹32,500, you save ₹5,500 on this quote. " (empty when nothing is saved). */
    public static function savingsLine(Quotation $q): string
    {
        if ($q->savings_paise <= 0) {
            return '';
        }

        return 'MRP '.Money::format($q->total_paise + $q->savings_paise).' → our price '.Money::format($q->total_paise)
            .', you save '.Money::format($q->savings_paise).' on this quote. ';
    }

    /** https://wa.me/919823107676?text=… (works for WhatsApp Web, Desktop and the phone app). */
    public static function waLink(string $mobile, string $text): string
    {
        $e164 = (string) Phone::normalize($mobile);
        $manual = 'App\\Services\\WhatsApp\\WhatsApp';
        if (class_exists($manual) && method_exists($manual, 'manualLink')) {
            return app($manual)->manualLink($e164, $text);
        }

        return 'https://wa.me/'.ltrim($e164, '+').'?text='.rawurlencode($text);
    }

    public static function telLink(string $mobile): string
    {
        return 'tel:'.Phone::normalize($mobile);
    }

    /** Signed link to the quotation PDF, valid 60 days past the quote's validity. */
    public static function pdfLink(Quotation $q): string
    {
        $until = Carbon::parse($q->valid_until ?? now())->addDays(60)->endOfDay();

        return URL::temporarySignedRoute('quotations.public-pdf', $until, ['quotation' => $q->id]);
    }

    public static function mobileFor(Model $subject): string
    {
        return $subject instanceof Lead ? (string) $subject->mobile : (string) $subject->customer?->mobile;
    }

    private function fill(string $template, array $vars): string
    {
        $first = Str::of((string) ($vars['name'] ?? ''))->trim()->before(' ')->toString();
        $vars['name'] = $first !== '' ? $first : 'there';
        $vars['shop'] = (string) Setting::get('shop.name');

        $text = preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $template);

        // Tidy what an empty placeholder leaves behind ("Quotation: " with no link, double spaces).
        $text = preg_replace('/^\s*Quotation:\s*$/m', '', $text);
        $text = preg_replace("/[ \t]{2,}/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
