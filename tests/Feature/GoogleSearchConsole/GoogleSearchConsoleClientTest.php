<?php

declare(strict_types=1);

use App\GoogleSearchConsole\GoogleSearchConsoleClient;
use App\GoogleSearchConsole\GoogleSearchConsoleException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function makeClient(): GoogleSearchConsoleClient
{
    return new GoogleSearchConsoleClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
        redirectUri: 'https://ingenio.la/admin/gsc/callback',
    );
}

test('authorizationUrl arma la url con los parametros correctos', function () {
    $url = makeClient()->authorizationUrl('el-state');

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->and($url)->toContain('client_id=test-client-id')
        ->and($url)->toContain('access_type=offline')
        ->and($url)->toContain('prompt=consent')
        ->and($url)->toContain('state=el-state')
        ->and($url)->toContain(urlencode('https://www.googleapis.com/auth/webmasters.readonly'));
});

test('exchangeCodeForToken manda grant_type authorization_code y parsea la respuesta', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'un-access-token',
            'refresh_token' => 'un-refresh-token',
            'expires_in' => 3599,
        ], 200),
    ]);

    $result = makeClient()->exchangeCodeForToken('el-codigo');

    Http::assertSent(fn (Request $request) => $request['grant_type'] === 'authorization_code' && $request['code'] === 'el-codigo');

    expect($result)->toBe([
        'access_token' => 'un-access-token',
        'refresh_token' => 'un-refresh-token',
        'expires_in' => 3599,
    ]);
});

test('exchangeCodeForToken lanza excepcion si google responde error', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    expect(fn () => makeClient()->exchangeCodeForToken('codigo-invalido'))
        ->toThrow(GoogleSearchConsoleException::class);
});

test('refreshAccessToken manda grant_type refresh_token', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'nuevo-token', 'expires_in' => 3600], 200),
    ]);

    $result = makeClient()->refreshAccessToken('el-refresh-token');

    Http::assertSent(fn (Request $request) => $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'el-refresh-token');

    expect($result)->toBe(['access_token' => 'nuevo-token', 'expires_in' => 3600]);
});

test('listSites parsea siteEntry', function () {
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites' => Http::response([
            'siteEntry' => [
                ['siteUrl' => 'sc-domain:ejemplo.com', 'permissionLevel' => 'siteOwner'],
                ['siteUrl' => 'https://otro.com/', 'permissionLevel' => 'siteUnverifiedUser'],
            ],
        ], 200),
    ]);

    $sites = makeClient()->listSites('un-access-token');

    expect($sites)->toBe([
        ['siteUrl' => 'sc-domain:ejemplo.com', 'permissionLevel' => 'siteOwner'],
        ['siteUrl' => 'https://otro.com/', 'permissionLevel' => 'siteUnverifiedUser'],
    ]);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer un-access-token'));
});

test('querySearchAnalytics manda las dimensiones y parsea rows', function () {
    Http::fake([
        '*/searchAnalytics/query' => Http::response([
            'rows' => [
                ['keys' => ['2026-08-20', 'zapatos deportivos'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.0353, 'position' => 8.4],
            ],
        ], 200),
    ]);

    $rows = makeClient()->querySearchAnalytics('un-access-token', 'sc-domain:ejemplo.com', '2026-08-20', '2026-08-27', ['date', 'query']);

    expect($rows)->toBe([
        ['keys' => ['2026-08-20', 'zapatos deportivos'], 'clicks' => 12.0, 'impressions' => 340.0, 'ctr' => 0.0353, 'position' => 8.4],
    ]);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), rawurlencode('sc-domain:ejemplo.com'))
            && $request['dimensions'] === ['date', 'query'];
    });
});

test('matchesProjectDomain reconoce dominios de tipo sc-domain y url-prefix', function () {
    expect(GoogleSearchConsoleClient::matchesProjectDomain('sc-domain:ejemplo.com', 'ejemplo.com'))->toBeTrue()
        ->and(GoogleSearchConsoleClient::matchesProjectDomain('https://ejemplo.com/', 'ejemplo.com'))->toBeTrue()
        ->and(GoogleSearchConsoleClient::matchesProjectDomain('http://ejemplo.com/', 'ejemplo.com'))->toBeTrue()
        ->and(GoogleSearchConsoleClient::matchesProjectDomain('sc-domain:otro.com', 'ejemplo.com'))->toBeFalse()
        ->and(GoogleSearchConsoleClient::matchesProjectDomain('https://otro.com/', 'ejemplo.com'))->toBeFalse();
});
