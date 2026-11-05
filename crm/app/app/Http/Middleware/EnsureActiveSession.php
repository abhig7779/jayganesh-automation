<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs staff out when their login is switched off, or after the idle time for their role
 * (30 min for Owner/Finance, 8 h for the counter).
 */
class EnsureActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // API calls with a bearer token have no session: only the on/off switch applies.
        if ($user && ! $request->hasSession()) {
            // The "Website & ads (API)" service account is switched off for logins on purpose
            // (no role, no screens); its keys may only create leads (crm/LEADS-API.md).
            $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
            $leadIntakeOnly = $token && method_exists($token, 'can') && $token->can('leads:create')
                && ! $token->can('*') && $user->roles()->doesntExist();
            abort_unless($user->is_active || $leadIntakeOnly, 403, 'This login is switched off.');

            return $next($request);
        }

        if ($user) {
            $lastSeen = (int) $request->session()->get('last_seen_at', now()->timestamp);
            $idleTooLong = now()->timestamp - $lastSeen > $user->idleTimeoutMinutes() * 60;

            if (! $user->is_active || $idleTooLong) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'login' => $user->is_active ? 'You were signed out after being idle. Please sign in again.' : 'This login is switched off.',
                ]);
            }

            $request->session()->put('last_seen_at', now()->timestamp);
        }

        return $next($request);
    }
}
