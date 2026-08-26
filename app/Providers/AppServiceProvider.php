<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Este proyecto no tiene una ruta 'login' genérica: hay dos
         * logins separados, el del panel Filament
         * ('filament.admin.auth.login') y el del portal de cliente
         * ('portal.login', Fase 1, paso 11). Sin esto, cualquier ruta
         * fuera de Filament protegida con el middleware `auth` (ej.
         * DownloadReportController) lanza un RouteNotFoundException
         * para un visitante no autenticado, en vez de redirigirlo a
         * iniciar sesión — y hay que elegir el login correcto según de
         * qué zona viene el request.
         */
        Authenticate::redirectUsing(fn (Request $request) => $request->is('portal', 'portal/*')
            ? route('portal.login')
            : route('filament.admin.auth.login'));
    }
}
