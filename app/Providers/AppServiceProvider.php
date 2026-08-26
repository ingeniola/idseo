<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
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
         * Este proyecto no tiene una ruta 'login' genérica: el único
         * login es el del panel Filament ('filament.admin.auth.login').
         * Sin esto, cualquier ruta fuera de Filament protegida con el
         * middleware `auth` (ej. DownloadReportController) lanza un
         * RouteNotFoundException para un visitante no autenticado, en
         * vez de redirigirlo a iniciar sesión.
         */
        Authenticate::redirectUsing(fn () => route('filament.admin.auth.login'));
    }
}
