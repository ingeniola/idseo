<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\GoogleSearchConsole\GoogleSearchConsoleClient;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Arranca el flujo OAuth (Fase 2, sección 5 del SPEC). El `state` es
 * un payload encriptado (project_id + nonce), no solo un nonce suelto:
 * así SearchConsoleCallbackController sabe a qué proyecto conectar la
 * respuesta sin depender de un segmento dinámico en la redirect_uri
 * (la redirect_uri debe ser fija y coincidir exactamente con la
 * registrada en Google Cloud Console). El nonce, guardado aparte en
 * sesión, es la protección CSRF de verdad: el payload encriptado por
 * sí solo prueba que Ingenio lo generó, pero no que fue *esta* sesión
 * la que inició el flujo.
 */
class ConnectSearchConsoleController extends Controller
{
    public function __construct(
        private readonly GoogleSearchConsoleClient $client,
    ) {}

    public function __invoke(Request $request, Project $project): RedirectResponse
    {
        $nonce = Str::random(40);
        $request->session()->put('gsc_oauth_nonce', $nonce);

        $state = encrypt(['project_id' => $project->id, 'nonce' => $nonce]);

        return redirect()->away($this->client->authorizationUrl($state));
    }
}
