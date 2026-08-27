<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\GoogleSearchConsole\GoogleSearchConsoleClient;
use App\Models\Project;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * redirect_uri fija (Fase 2, sección 5 del SPEC) — recibe el `state`
 * encriptado que armó ConnectSearchConsoleController para saber a qué
 * proyecto pertenece esta respuesta, sin depender de un segmento
 * dinámico en la URL. Nunca persiste una conexión sin verificar antes,
 * contra la respuesta real de Google, que la cuenta que autorizó
 * tiene acceso a una propiedad que coincide con el dominio del
 * proyecto — conectar la cuenta equivocada sería peor que no conectar
 * nada.
 */
class SearchConsoleCallbackController extends Controller
{
    public function __construct(
        private readonly GoogleSearchConsoleClient $client,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $project = $this->resolveProjectFromState($request);

        if ($project === null) {
            abort(403, 'Estado de OAuth inválido o expirado.');
        }

        $redirectTo = route('filament.admin.resources.projects.edit', ['record' => $project]);

        if ($request->query('error') !== null) {
            return $this->fail($redirectTo, 'El acceso fue rechazado en Google.');
        }

        $code = (string) $request->query('code');
        $tokenData = $this->client->exchangeCodeForToken($code);

        if ($tokenData['refresh_token'] === null) {
            Log::warning('gsc.callback.missing_refresh_token', ['project_id' => $project->id]);

            return $this->fail($redirectTo, 'Google no devolvió un refresh token. Vuelva a intentarlo.');
        }

        $sites = $this->client->listSites($tokenData['access_token']);

        $matchedSite = collect($sites)
            ->first(fn (array $site) => $site['permissionLevel'] !== 'siteUnverifiedUser'
                && GoogleSearchConsoleClient::matchesProjectDomain($site['siteUrl'], $project->domain));

        if ($matchedSite === null) {
            return $this->fail($redirectTo, "La cuenta de Google conectada no tiene acceso verificado a {$project->domain} en Search Console.");
        }

        /** @var User $user */
        $user = $request->user();

        SearchConsoleConnection::query()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'site_url' => $matchedSite['siteUrl'],
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expires_at' => now()->addSeconds($tokenData['expires_in']),
                'connected_by' => $user->id,
            ],
        );

        Notification::make()
            ->title("Search Console conectado ({$matchedSite['siteUrl']}).")
            ->success()
            ->send();

        return redirect($redirectTo);
    }

    private function resolveProjectFromState(Request $request): ?Project
    {
        $state = $request->query('state');

        if (! is_string($state)) {
            return null;
        }

        try {
            $payload = decrypt($state);
        } catch (Throwable) {
            return null;
        }

        // No basta con confiar en el shape de decrypt() vía un @var: es
        // un payload que llega desde afuera (rebota por Google), hay que
        // validarlo de verdad antes de usarlo.
        if (! is_array($payload)
            || ! is_string($payload['nonce'] ?? null)
            || ! is_int($payload['project_id'] ?? null)) {
            return null;
        }

        $expectedNonce = $request->session()->pull('gsc_oauth_nonce');

        if ($payload['nonce'] !== $expectedNonce) {
            return null;
        }

        return Project::query()->find($payload['project_id']);
    }

    private function fail(string $redirectTo, string $message): RedirectResponse
    {
        Notification::make()
            ->title($message)
            ->danger()
            ->send();

        return redirect($redirectTo);
    }
}
