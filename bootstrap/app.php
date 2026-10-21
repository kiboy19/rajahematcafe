<?php

use App\Http\Middleware\EnsureCustomerEmailIsVerifiedIfRequired;
use App\Http\Middleware\EnsureStaffIsActive;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff.active' => EnsureStaffIsActive::class,
            'role' => RequireRole::class,
            'customer.verified' => EnsureCustomerEmailIsVerifiedIfRequired::class,
        ]);

        /*
         * Endpoint /api dipakai SPA React. Autentikasi masih memakai guard session
         * (`web` untuk staf, `customer` untuk pelanggan), jadi grup api perlu
         * cookie, session, dan proteksi CSRF yang sama seperti halaman web.
         */
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        // Webhook Midtrans: dipanggil server-to-server tanpa session cookie, jadi CSRF harus dilewati.
        // Keamanan dijamin verifikasi signature SHA512 di MidtransWebhookController.
        $middleware->validateCsrfTokens(except: ['midtrans/webhook']);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('customer.*')
            ? route('customer.login')
            : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('customer.*')
            ? route('customer.home')
            : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );
    })->create();
