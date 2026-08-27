<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // DataForSEO no manda un token CSRF de Laravel; la ruta se
        // protege con el token propio en la query string (ver
        // DataForSeoPostbackController).
        $middleware->validateCsrfTokens(except: [
            'webhooks/dataforseo/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sección 12 del SPEC: "Sentry o Flare para excepciones". Sin
        // efecto si SENTRY_LARAVEL_DSN no está configurado (el SDK no
        // manda nada a ningún lado en ese caso).
        Integration::handles($exceptions);
    })->create();
