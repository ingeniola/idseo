<?php

declare(strict_types=1);

use App\GoogleSearchConsole\GoogleSearchConsoleClient;
use App\Jobs\SyncSearchConsoleData;
use App\Models\Project;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('sincroniza metricas con dimensiones date y query, y hace upsert', function () {
    $project = Project::factory()->create();
    $connection = SearchConsoleConnection::factory()->create([
        'project_id' => $project->id,
        'site_url' => 'sc-domain:ejemplo.com',
        'expires_at' => now()->addHour(),
    ]);

    // fakeSequence (no fake() repetido con el mismo patrón, que no se
    // reemplaza): la primera corrida trae los datos "iniciales", la
    // segunda simula que Search Console ya los revisó/finalizó.
    Http::fakeSequence('*/searchAnalytics/query')
        ->push([
            'rows' => [
                ['keys' => ['2026-08-20', 'zapatos deportivos'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.0353, 'position' => 8.4],
                ['keys' => ['2026-08-21', 'zapatos deportivos'], 'clicks' => 5, 'impressions' => 150, 'ctr' => 0.0333, 'position' => 9.1],
            ],
        ], 200)
        ->push([
            'rows' => [
                ['keys' => ['2026-08-20', 'zapatos deportivos'], 'clicks' => 20, 'impressions' => 400, 'ctr' => 0.05, 'position' => 7.9],
            ],
        ], 200);

    app(SyncSearchConsoleData::class)->handle(app(GoogleSearchConsoleClient::class));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), rawurlencode('sc-domain:ejemplo.com'))
        && $request['dimensions'] === ['date', 'query']);

    expect(SearchConsoleMetric::query()->where('project_id', $project->id)->count())->toBe(2);

    $row = SearchConsoleMetric::query()->where('date', '2026-08-20')->first();
    expect($row->clicks)->toBe(12)
        ->and($row->impressions)->toBe(340)
        ->and((float) $row->ctr)->toEqual(0.0353);

    // Volver a correr con datos revisados: el mismo día+query se actualiza, no se duplica.
    app(SyncSearchConsoleData::class)->handle(app(GoogleSearchConsoleClient::class));

    expect(SearchConsoleMetric::query()->where('date', '2026-08-20')->count())->toBe(1)
        ->and(SearchConsoleMetric::query()->where('date', '2026-08-20')->first()->clicks)->toBe(20);
});

test('refresca el access token si esta vencido y guarda el nuevo', function () {
    $project = Project::factory()->create();
    $connection = SearchConsoleConnection::factory()->create([
        'project_id' => $project->id,
        'access_token' => 'token-viejo',
        'refresh_token' => 'el-refresh-token',
        'expires_at' => now()->subMinute(),
    ]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-nuevo', 'expires_in' => 3600], 200),
        '*/searchAnalytics/query' => Http::response(['rows' => []], 200),
    ]);

    app(SyncSearchConsoleData::class)->handle(app(GoogleSearchConsoleClient::class));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['refresh_token'] === 'el-refresh-token');
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer token-nuevo'));

    expect($connection->fresh()->access_token)->toBe('token-nuevo')
        ->and($connection->fresh()->expires_at->isFuture())->toBeTrue();
});

test('no falla el job entero si un proyecto falla al refrescar', function () {
    $project = Project::factory()->create();
    SearchConsoleConnection::factory()->create([
        'project_id' => $project->id,
        'expires_at' => now()->subMinute(),
    ]);

    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    app(SyncSearchConsoleData::class)->handle(app(GoogleSearchConsoleClient::class));

    expect(SearchConsoleMetric::query()->count())->toBe(0);
});
