<?php

use App\Http\Controllers\ConnectSearchConsoleController;
use App\Http\Controllers\DataForSeoPostbackController;
use App\Http\Controllers\DownloadReportController;
use App\Http\Controllers\PortalLogoutController;
use App\Http\Controllers\SearchConsoleCallbackController;
use App\Http\Middleware\EnsureUserIsClient;
use App\Http\Middleware\EnsureUserIsInternal;
use App\Livewire\Portal\Login as PortalLogin;
use App\Livewire\Portal\ProjectList as PortalProjectList;
use App\Livewire\Portal\ProjectShow as PortalProjectShow;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/dataforseo/{type}', DataForSeoPostbackController::class)
    ->name('webhooks.dataforseo.postback');

Route::get('/reports/{report}/download', DownloadReportController::class)
    ->middleware('auth')
    ->name('reports.download');

/**
 * Google Search Console (Fase 2, sección 5 del SPEC): rutas planas de
 * Laravel, no recursos de Filament, porque OAuth necesita
 * redirecciones de página completa (fuera del ciclo de vida de
 * Livewire). EnsureUserIsInternal es la contraparte de
 * EnsureUserIsClient del portal: sin ella, un usuario `client`
 * autenticado podría alcanzar estas rutas igual que cualquier otra
 * ruta con solo `auth`. gsc/callback NO lleva {project} en la URL:
 * tiene que ser una redirect_uri fija, idéntica a la registrada en
 * Google Cloud Console — SearchConsoleCallbackController recupera el
 * proyecto desde el `state` encriptado. Desconectar NO vive aquí:
 * no necesita salir a Google, así que es una Action normal de Filament
 * dentro de SearchConsoleRelationManager, no una ruta aparte.
 */
Route::middleware(['auth', EnsureUserIsInternal::class])->group(function () {
    Route::get('/admin/projects/{project}/gsc/connect', ConnectSearchConsoleController::class)
        ->name('gsc.connect');
    Route::get('/admin/gsc/callback', SearchConsoleCallbackController::class)
        ->name('gsc.callback');
});

/**
 * Portal de cliente (Fase 1, paso 11 del SPEC): plano Livewire, sin
 * Filament — panel completamente separado del interno. El login no
 * lleva middleware `guest`: Login::mount() redirige si ya hay sesión
 * de un usuario `client`, y authenticate() rechaza cualquier otro rol
 * con el mismo mensaje que unas credenciales incorrectas.
 */
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/login', PortalLogin::class)->name('login');
    Route::post('/logout', PortalLogoutController::class)->name('logout');

    Route::middleware(['auth', EnsureUserIsClient::class])->group(function () {
        Route::get('/', PortalProjectList::class)->name('dashboard');
        Route::get('/projects/{project}', PortalProjectShow::class)->name('projects.show');
    });
});
