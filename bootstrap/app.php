<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\RecordVisit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stripe and Paystack can't send a form token; their webhooks are checked by signature instead.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // The light/dark choice is set by the browser, so it is a plain (unencrypted) cookie.
        $middleware->encryptCookies(except: ['hr-theme']);

        $middleware->web(append: [RecordVisit::class]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'staff' => EnsureUserIsStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
