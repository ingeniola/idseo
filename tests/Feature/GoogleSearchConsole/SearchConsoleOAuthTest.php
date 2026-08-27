<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeGoogleTokenAndSites(array $siteEntries): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'un-access-token',
            'refresh_token' => 'un-refresh-token',
            'expires_in' => 3600,
        ], 200),
        'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => $siteEntries], 200),
    ]);
}

test('conectar redirige a google con un state valido', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create();

    $response = $this->actingAs($admin)->get(route('gsc.connect', $project));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://accounts.google.com/o/oauth2/v2/auth');
    expect(session('gsc_oauth_nonce'))->not->toBeNull();
});

test('un usuario de cliente no puede iniciar la conexion', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $project = Project::factory()->create();

    $this->actingAs($client)->get(route('gsc.connect', $project))->assertForbidden();
});

test('el flujo completo conecta el proyecto con el sitio que coincide con su dominio', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    fakeGoogleTokenAndSites([
        ['siteUrl' => 'https://otro.com/', 'permissionLevel' => 'siteOwner'],
        ['siteUrl' => 'sc-domain:ejemplo.com', 'permissionLevel' => 'siteOwner'],
    ]);

    $connectResponse = $this->actingAs($admin)->get(route('gsc.connect', $project));
    $state = parse_url_query_param($connectResponse->headers->get('Location'), 'state');

    $callbackResponse = $this->get(route('gsc.callback', ['code' => 'un-codigo', 'state' => $state]));

    $callbackResponse->assertRedirect(route('filament.admin.resources.projects.edit', ['record' => $project]));

    $connection = SearchConsoleConnection::query()->where('project_id', $project->id)->first();

    expect($connection)->not->toBeNull()
        ->and($connection->site_url)->toBe('sc-domain:ejemplo.com')
        ->and($connection->access_token)->toBe('un-access-token')
        ->and($connection->refresh_token)->toBe('un-refresh-token')
        ->and($connection->connected_by)->toBe($admin->id);
});

test('no persiste nada si ninguna propiedad coincide con el dominio del proyecto', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    fakeGoogleTokenAndSites([
        ['siteUrl' => 'sc-domain:otro.com', 'permissionLevel' => 'siteOwner'],
    ]);

    $connectResponse = $this->actingAs($admin)->get(route('gsc.connect', $project));
    $state = parse_url_query_param($connectResponse->headers->get('Location'), 'state');

    $this->get(route('gsc.callback', ['code' => 'un-codigo', 'state' => $state]));

    expect(SearchConsoleConnection::query()->count())->toBe(0);
});

test('ignora una propiedad que coincide pero no esta verificada', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    fakeGoogleTokenAndSites([
        ['siteUrl' => 'sc-domain:ejemplo.com', 'permissionLevel' => 'siteUnverifiedUser'],
    ]);

    $connectResponse = $this->actingAs($admin)->get(route('gsc.connect', $project));
    $state = parse_url_query_param($connectResponse->headers->get('Location'), 'state');

    $this->get(route('gsc.callback', ['code' => 'un-codigo', 'state' => $state]));

    expect(SearchConsoleConnection::query()->count())->toBe(0);
});

test('un state con nonce que no coincide con la sesion se rechaza', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create();

    // State válido en su forma (encriptado por la propia app) pero de
    // un flujo distinto: la sesión nunca guardó este nonce.
    $state = encrypt(['project_id' => $project->id, 'nonce' => 'nonce-ajeno']);

    $this->actingAs($admin)
        ->get(route('gsc.callback', ['code' => 'un-codigo', 'state' => $state]))
        ->assertForbidden();

    expect(SearchConsoleConnection::query()->count())->toBe(0);
});

test('el usuario rechaza el acceso en google', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create();

    $connectResponse = $this->actingAs($admin)->get(route('gsc.connect', $project));
    $state = parse_url_query_param($connectResponse->headers->get('Location'), 'state');

    $callbackResponse = $this->get(route('gsc.callback', ['error' => 'access_denied', 'state' => $state]));

    $callbackResponse->assertRedirect(route('filament.admin.resources.projects.edit', ['record' => $project]));
    expect(SearchConsoleConnection::query()->count())->toBe(0);
});

function parse_url_query_param(string $url, string $param): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return (string) $query[$param];
}
