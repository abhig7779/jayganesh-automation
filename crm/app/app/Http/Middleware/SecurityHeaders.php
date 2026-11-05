<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response, and http -> https in production.
 *
 * CSP decision (crm/DEPLOY.md, "Security headers"):
 *  - We use the normal Alpine build, which evaluates x-data / @click expressions with
 *    new Function(), so script-src needs 'unsafe-eval'. htmx's hx-on needs it too.
 *    Switching to @alpinejs/csp would remove it, but then every inline Alpine expression in
 *    every module would have to move into Alpine.data(); not worth it for a staff-only app.
 *  - Inline <script> blocks are blocked unless they carry the per-request nonce:
 *    <script nonce="{{ Vite::cspNonce() }}">. Prefer code in resources/js.
 *  - style-src allows 'unsafe-inline' (style="" attributes and htmx's indicator styles).
 *  - SECURITY_CSP=report-only turns the policy into a warning-only header if something breaks.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $production = app()->environment('production');

        if ($production && config('ops.force_https') && ! $request->isSecure()
            && in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
        }

        $mode = config('ops.csp', 'enforce');
        if ($mode !== 'off') {
            Vite::useCspNonce();
        }

        $response = $next($request);

        $h = $response->headers;
        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->remove('X-Powered-By');

        if ($request->isSecure() || $production) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($mode !== 'off') {
            $header = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $h->set($header, $this->policy($production));
        }

        return $response;
    }

    private function policy(bool $production): string
    {
        $nonce = Vite::cspNonce();
        $script = ["'self'", "'nonce-{$nonce}'", "'unsafe-eval'"];
        $style = ["'self'", "'unsafe-inline'"];
        $connect = ["'self'"];

        // `npm run dev` on a developer machine serves assets from the Vite server.
        if (! $production && Vite::isRunningHot()) {
            $hot = rtrim((string) @file_get_contents(Vite::hotFile()));
            $ws = preg_replace('#^http#', 'ws', $hot);
            array_push($script, $hot);
            array_push($style, $hot);
            array_push($connect, $hot, $ws);
        }

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "manifest-src 'self'",
        ];
        if ($production) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
