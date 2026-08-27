<?php

declare(strict_types=1);

namespace App\GoogleSearchConsole;

use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo para OAuth2 de Google y la API de Search Console
 * (Fase 2, sección 5 del SPEC: "OAuth propio, datos gratis"). No usa
 * el SDK oficial `google/apiclient` (pesado — trae clientes generados
 * para cientos de APIs de Google que no se usan) ni Socialite (pensado
 * para "iniciar sesión como", no para conectar una integración por
 * proyecto): son 3 endpoints REST bien documentados y estables, así
 * que un cliente HTTP propio con Http::fake()-able en los tests sigue
 * el mismo criterio que DataForSeoClient (Fase 1) — liviano y
 * verificado contra la documentación pública antes de escribir el
 * mapeo de campos.
 *
 * access_type=offline + prompt=consent en la URL de autorización es
 * obligatorio para que Google devuelva un refresh_token: sin
 * prompt=consent, un usuario que ya autorizó la app antes no vuelve a
 * recibir uno.
 */
class GoogleSearchConsoleClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API_BASE_URL = 'https://www.googleapis.com/webmasters/v3/';

    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {}

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if ($response->failed()) {
            throw new GoogleSearchConsoleException("Fallo al intercambiar el código de autorización: HTTP {$response->status()}.");
        }

        $data = $response->json();

        return [
            'access_token' => (string) ($data['access_token'] ?? ''),
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            throw new GoogleSearchConsoleException("Fallo al refrescar el access token: HTTP {$response->status()}.");
        }

        $data = $response->json();

        return [
            'access_token' => (string) ($data['access_token'] ?? ''),
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    /**
     * @return array<int, array{siteUrl: string, permissionLevel: string}>
     */
    public function listSites(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::API_BASE_URL.'sites');

        if ($response->failed()) {
            throw new GoogleSearchConsoleException("Fallo al listar propiedades de Search Console: HTTP {$response->status()}.");
        }

        $entries = $response->json('siteEntry') ?? [];

        return array_map(
            fn (array $entry) => [
                'siteUrl' => (string) ($entry['siteUrl'] ?? ''),
                'permissionLevel' => (string) ($entry['permissionLevel'] ?? ''),
            ],
            $entries,
        );
    }

    /**
     * @param  array<int, string>  $dimensions
     * @return array<int, array{keys: array<int, string>, clicks: float, impressions: float, ctr: float, position: float}>
     */
    public function querySearchAnalytics(
        string $accessToken,
        string $siteUrl,
        string $startDate,
        string $endDate,
        array $dimensions,
        int $rowLimit = 1000,
    ): array {
        $response = Http::withToken($accessToken)->post(
            self::API_BASE_URL.'sites/'.rawurlencode($siteUrl).'/searchAnalytics/query',
            [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => $dimensions,
                'rowLimit' => $rowLimit,
            ],
        );

        if ($response->failed()) {
            throw new GoogleSearchConsoleException("Fallo al consultar searchAnalytics: HTTP {$response->status()}.");
        }

        $rows = $response->json('rows') ?? [];

        return array_map(
            fn (array $row) => [
                'keys' => $row['keys'] ?? [],
                'clicks' => (float) ($row['clicks'] ?? 0),
                'impressions' => (float) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => (float) ($row['position'] ?? 0),
            ],
            $rows,
        );
    }

    /**
     * Compara una `siteUrl` de Search Console (Domain property:
     * "sc-domain:example.com", o URL-prefix: "https://example.com/")
     * contra `projects.domain` (guardado como "example.com", sin
     * esquema). Domain property cubre todos los subdominios/esquemas a
     * la vez, así que solo hace falta comparar el nombre de dominio en
     * ese caso; URL-prefix es más estricto (esquema exacto), pero
     * igual se compara solo por dominio: elegir cuál esquema promete
     * el proyecto no es responsabilidad de este matcher.
     */
    public static function matchesProjectDomain(string $siteUrl, string $projectDomain): bool
    {
        $projectDomain = strtolower(rtrim($projectDomain, '/'));

        if ($siteUrl === "sc-domain:{$projectDomain}") {
            return true;
        }

        $withoutScheme = strtolower((string) preg_replace('#^https?://#', '', rtrim($siteUrl, '/')));

        return $withoutScheme === $projectDomain;
    }
}
