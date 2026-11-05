<?php

namespace App\Services\Leads;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads one Meta lead-ad form submission (GET /{leadgen_id}) and turns its answers into the
 * fields LeadIntakeService takes. Meta names standard questions full_name / phone_number /
 * email / city; custom questions keep the shop's own wording ("which_car_do_you_have?").
 */
class MetaLeadFetcher
{
    /** @return array{data: array, raw: array} */
    public function fetch(string $leadgenId): array
    {
        $token = (string) config('leads.meta.page_token');
        if ($token === '') {
            throw new RuntimeException('META_LEADS_PAGE_TOKEN is not set.');
        }
        $version = (string) config('leads.meta.graph_version', 'v23.0');

        $response = Http::timeout(15)->retry(2, 500, throw: false)
            ->withToken($token)
            ->get("https://graph.facebook.com/{$version}/".rawurlencode($leadgenId), [
                'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_name,campaign_name,form_id,platform',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Meta lead fetch failed ('.$response->status().'): '.($response->json('error.message') ?? 'no details'));
        }

        $raw = $response->json() ?? [];

        return ['data' => $this->map($raw), 'raw' => $raw];
    }

    /** Graph API lead → LeadIntakeService fields. */
    public function map(array $lead): array
    {
        $answers = [];
        foreach ($lead['field_data'] ?? [] as $f) {
            $answers[mb_strtolower((string) ($f['name'] ?? ''))] = trim(implode(', ', (array) ($f['values'] ?? [])));
        }

        $take = function (array $patterns) use (&$answers): ?string {
            foreach ($answers as $key => $value) {
                foreach ($patterns as $p) {
                    if ($value !== '' && preg_match($p, $key)) {
                        unset($answers[$key]);

                        return $value;
                    }
                }
            }

            return null;
        };

        $name = $take(['/^full_?name$/', '/^name$/']);
        if ($name === null) {
            $name = trim(($take(['/^first_?name$/']) ?? '').' '.($take(['/^last_?name$/']) ?? '')) ?: null;
        }
        $mobile = $take(['/^phone_?number$/', '/phone|mobile|whatsapp|contact/']);
        $car = $take(['/car|vehicle|model|gadi/']);
        $interest = $take(['/service|interest|product|looking|need|want|requirement/']);
        $area = $take(['/^city$/', '/area|location|locality/']);
        $answers = array_diff_key($answers, array_flip(['email']));

        $rest = [];
        if ($area) {
            $rest[] = 'Area: '.$area;
        }
        foreach ($answers as $key => $value) {
            if ($value !== '') {
                $rest[] = ucfirst(str_replace('_', ' ', rtrim($key, '?'))).': '.$value;
            }
        }

        $platform = strtolower((string) ($lead['platform'] ?? ''));

        return [
            'name' => $name,
            'mobile' => $mobile,
            'car' => $car,
            'interest' => $interest ?? ($lead['campaign_name'] ?? null),
            'message' => $rest ? implode(' · ', $rest) : null,
            'source' => 'meta_lead_ad',
            'utm_source' => $platform === 'ig' ? 'instagram' : 'facebook',
            'utm_medium' => 'lead_ad',
            'utm_campaign' => $lead['campaign_name'] ?? null,
            'utm_content' => $lead['ad_name'] ?? null,
            'utm_term' => $lead['adset_name'] ?? null,
        ];
    }
}
