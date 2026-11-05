<?php

namespace App\Support;

/**
 * Every editable setting, with its label, form type, validation and default.
 * Add a field here and it appears on the Settings page; nothing else to change.
 */
final class SettingsSchema
{
    public static function fields(): array
    {
        return [
            // Shop
            'shop.name' => ['group' => 'Shop', 'label' => 'Shop name', 'type' => 'text', 'rules' => 'required|string|max:120', 'default' => 'Jay Ganesh Car Accessories'],
            'shop.address' => ['group' => 'Shop', 'label' => 'Address (printed on bills)', 'type' => 'textarea', 'rules' => 'required|string|max:400', 'default' => 'Kawadipat Toll Naka, Loni Kalbhor, Pune, Maharashtra'],
            'shop.phone' => ['group' => 'Shop', 'label' => 'Phone on bills', 'type' => 'text', 'rules' => 'nullable|string|max:20', 'default' => '+91 99813 97676'],
            'shop.email' => ['group' => 'Shop', 'label' => 'Email on bills', 'type' => 'email', 'rules' => 'nullable|email|max:120', 'default' => null],
            'shop.maps_url' => ['group' => 'Shop', 'label' => 'Google Maps link', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'default' => null],

            // Tax & billing
            'tax.gstin' => ['group' => 'Tax & billing', 'label' => 'GSTIN (leave empty if not registered)', 'type' => 'text', 'rules' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'], 'default' => null],
            'tax.state_code' => ['group' => 'Tax & billing', 'label' => 'Shop state code', 'type' => 'text', 'rules' => 'required|digits:2', 'default' => '27'],
            'tax.default_gst_rate' => ['group' => 'Tax & billing', 'label' => 'Default GST % for new products', 'type' => 'number', 'rules' => 'required|numeric|in:0,5,12,18,28', 'default' => 18],
            'billing.invoice_prefix' => ['group' => 'Tax & billing', 'label' => 'Invoice prefix', 'type' => 'text', 'rules' => 'required|alpha_dash|max:10', 'default' => 'JG'],
            'billing.quotation_prefix' => ['group' => 'Tax & billing', 'label' => 'Quotation prefix', 'type' => 'text', 'rules' => 'required|alpha_dash|max:10', 'default' => 'JGQ'],
            'billing.quotation_valid_days' => ['group' => 'Tax & billing', 'label' => 'Quotation valid for (days)', 'type' => 'number', 'rules' => 'required|integer|min:1|max:90', 'default' => 7],
            'billing.invoice_footer' => ['group' => 'Tax & billing', 'label' => 'Invoice footer note', 'type' => 'textarea', 'rules' => 'nullable|string|max:500', 'default' => 'Thank you for choosing Jay Ganesh Car Accessories! Warranty as per brand terms. Goods once sold will not be taken back without the original bill.'],
            'billing.upi_id' => ['group' => 'Tax & billing', 'label' => 'UPI ID (for the QR on bills)', 'type' => 'text', 'rules' => ['nullable', 'regex:/^[\w.\-]{2,}@[a-zA-Z]{2,}$/'], 'default' => null],
            'billing.upi_name' => ['group' => 'Tax & billing', 'label' => 'UPI payee name', 'type' => 'text', 'rules' => 'nullable|string|max:60', 'default' => 'Jay Ganesh Car Accessories'],

            // WhatsApp
            'whatsapp.mode' => ['group' => 'WhatsApp', 'label' => 'Sending mode', 'type' => 'select', 'options' => ['manual' => 'Manual (opens WhatsApp with the message)', 'cloud_api' => 'Automatic (WhatsApp Cloud API)'], 'rules' => 'required|in:manual,cloud_api', 'default' => 'manual'],
            'whatsapp.business_number' => ['group' => 'WhatsApp', 'label' => 'Business WhatsApp number', 'type' => 'text', 'rules' => 'nullable|string|max:20', 'default' => '+91 98231 07676'],
            'whatsapp.template_invoice' => ['group' => 'WhatsApp', 'label' => 'Invoice template name', 'type' => 'text', 'rules' => 'nullable|alpha_dash|max:60', 'default' => 'invoice_ebill'],
            'whatsapp.template_quotation' => ['group' => 'WhatsApp', 'label' => 'Quotation template name', 'type' => 'text', 'rules' => 'nullable|alpha_dash|max:60', 'default' => 'quotation_send'],
            'whatsapp.auto_send_invoice' => ['group' => 'WhatsApp', 'label' => 'Send the e-bill automatically when a bill is saved', 'type' => 'checkbox', 'rules' => 'boolean', 'default' => true],

            // --- quotations: follow-up engine (UX-FLOWS §3.2) ---
            ...self::followUpFields(),
        ];
    }

    /**
     * Follow-up schedule and the WhatsApp texts staff send on each step. Placeholders:
     * {name} {car} {items} {amount} {valid_until} {pdf_link} {shop} {savings} {savings_line}.
     */
    private static function followUpFields(): array
    {
        // Left empty, each one falls back to its default (FollowUpMessages::template).
        $g = 'Quotations & follow-ups';
        $msg = fn (string $label, string $default) => ['group' => $g, 'label' => $label, 'type' => 'textarea', 'rules' => 'nullable|string|max:1000', 'default' => $default];

        return [
            'followups.schedule_days' => ['group' => $g, 'label' => 'Follow-up days after a quotation (comma separated)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:40', 'regex:/^\s*\d{1,2}(\s*,\s*\d{1,2}){0,5}\s*$/'], 'default' => '1, 3, 6, 10'],
            'followups.msg_day1' => $msg('WhatsApp message: 1st follow-up (soft check-in)', "Hello {name}, thank you for visiting {shop}. Here is the quotation for your {car}: {items}, total {amount} (incl. GST). {savings_line}\n\nView or download it here: {pdf_link}\n\nIt is valid until {valid_until}. If you have any question, just reply to this message."),
            'followups.msg_day3' => $msg('WhatsApp message: 2nd follow-up (answer doubts)', "Hello {name}, just checking if you had any questions about the quotation for your {car} ({items}, {amount}). {savings_line}We are happy to share photos of similar work we have done, or you can drop by and see it.\n\nQuotation: {pdf_link}"),
            'followups.msg_day6' => $msg('WhatsApp message: 3rd follow-up (expiring soon)', "Hello {name}, a gentle reminder that your quotation for the {car} ({amount}) is valid until {valid_until}. {savings_line}Reply here or call us and we will book a time that suits you.\n\nQuotation: {pdf_link}"),
            'followups.msg_day10' => $msg('WhatsApp message: last follow-up', "Hello {name}, this is our last check on your quotation for the {car} ({items}, {amount}). If you are still interested, reply here and we will do our best for you.\n\nQuotation: {pdf_link}\n\nThank you, {shop}"),
            'followups.msg_visit' => $msg('WhatsApp message: on the day of a booked visit', "Hello {name}, looking forward to seeing you today at {shop} for your {car}. Reply here if the time changes.\n\nQuotation: {pdf_link}"),
            'followups.msg_lead' => $msg('WhatsApp message: enquiry / asked price', 'Hello {name}, thank you for asking about {items} at {shop}, Kawadipat Toll Naka. {amount_line}Would you like us to send a detailed quotation, or book a time to visit? Just reply here.'),
            'quotations.terms' => ['group' => $g, 'label' => 'Terms printed on quotations', 'type' => 'textarea', 'rules' => 'nullable|string|max:1000', 'default' => "Prices include GST.\nFitting time is confirmed when you book.\nWarranty as per brand terms.\nPrices may change after the validity date."],
        ];
    }

    public static function groups(): array
    {
        return collect(self::fields())->groupBy('group', preserveKeys: true)->all();
    }
}
