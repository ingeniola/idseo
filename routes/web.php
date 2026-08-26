<?php

use App\Http\Controllers\DataForSeoPostbackController;
use App\Http\Controllers\DownloadReportController;
use App\Http\Controllers\PortalLogoutController;
use App\Http\Middleware\EnsureUserIsClient;
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
