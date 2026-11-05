<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Leads\LeadIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * POST /api/v1/leads: the website enquiry form (and anything else holding a key with the
 * `leads:create` ability). See crm/LEADS-API.md.
 */
class LeadIntakeController extends Controller
{
    public function store(Request $request, LeadIntakeService $intake): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        $tokenId = $token instanceof PersonalAccessToken ? $token->getKey() : null;

        if ($limited = $this->tooMany($request, $tokenId)) {
            return $limited;
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'max:30'],
            'interest' => ['nullable', 'string', 'max:300'],
            'car' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', Rule::in(array_keys(LeadIntakeService::SOURCES))],
            'utm_source' => ['nullable', 'string', 'max:150'],
            'utm_medium' => ['nullable', 'string', 'max:150'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'utm_term' => ['nullable', 'string', 'max:150'],
            'utm_content' => ['nullable', 'string', 'max:150'],
            'page_url' => ['nullable', 'string', 'max:500'],
        ], [
            'mobile.required' => 'Enter a mobile number.',
            'source.in' => 'source must be one of: website, meta_lead_ad, instagram, other.',
        ]);
        $data['source'] ??= 'website';

        $result = $intake->receive($data, [
            'channel' => 'api',
            'ip' => $request->ip(),
            'token_id' => $tokenId,
        ]);

        return response()->json([
            'ok' => true,
            'lead_id' => $result['lead']->id,
            'merged' => $result['merged'],
            'message' => $result['merged']
                ? 'Added to the enquiry this number made in the last 7 days.'
                : 'Enquiry saved. The shop will call or WhatsApp you today.',
        ], $result['merged'] ? 200 : 201);
    }

    private function tooMany(Request $request, ?int $tokenId): ?JsonResponse
    {
        $keys = [
            ['leads-api:ip:'.$request->ip(), (int) config('leads.api_per_minute_per_ip', 20), 60],
            ['leads-api:key:'.($tokenId ?? 'session:'.$request->user()?->id), (int) config('leads.api_per_hour_per_key', 300), 3600],
        ];
        foreach ($keys as [$key, $max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, max(1, $max))) {
                $retry = RateLimiter::availableIn($key);

                return response()->json(['ok' => false, 'message' => 'Too many enquiries. Try again in '.$retry.' seconds.'], 429)
                    ->header('Retry-After', (string) $retry);
            }
        }
        foreach ($keys as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }

        return null;
    }
}
