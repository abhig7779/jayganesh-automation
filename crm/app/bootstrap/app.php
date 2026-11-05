<?php

use App\Http\Middleware\EnsureActiveSession;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureActiveSession::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        // Trusted proxies come from config/trustedproxy.php (TRUSTED_PROXIES in .env). The default
        // is none, so a client can't fake its IP with X-Forwarded-For (audit H2, crm/DEPLOY.md).
        // Lets the web pages call /api/v1 with the normal login cookie (Sanctum SPA mode).
        // Requests from SANCTUM_STATEFUL_DOMAINS get session + CSRF; everyone else needs a token.
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class); // [devops] security headers + https redirect (crm/DEPLOY.md)
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API always answers in JSON (401/403/404/422), never with a redirect to /login.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
