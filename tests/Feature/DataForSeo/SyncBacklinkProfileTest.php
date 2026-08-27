<?php

declare(strict_types=1);

use App\DataForSeo\Backlinks\SyncBacklinkProfile;
use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\Models\Backlink;
use App\Models\BacklinkSummary;
use App\Models\Project;
use App\Models\ReferringDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function dataForSeoLiveResponse(array $result): array
{
    return [
        'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
        'cost' => 0.01, 'tasks_count' => 1, 'tasks_error' => 0,
        'tasks' => [[
            'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.01, 'result_count' => 1, 'path' => [], 'data' => [],
            'result' => [$result],
        ]],
    ];
}

test('sincroniza summary, referring_domains y backlinks del propio dominio', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/backlinks/summary/live' => Http::response(dataForSeoLiveResponse([
            'target' => 'ejemplo.com', 'rank' => 450, 'backlinks' => 1200,
            'referring_domains' => 80, 'referring_ips' => 60, 'broken_backlinks' => 5,
        ]), 200),
        '*/backlinks/referring_domains/live' => Http::response(dataForSeoLiveResponse([
            'items' => [
                ['domain' => 'otrositio.com', 'rank' => 300, 'backlinks' => 12, 'first_seen' => '2025-01-15 00:00:00 +00:00'],
            ],
        ]), 200),
        '*/backlinks/backlinks/live' => Http::response(dataForSeoLiveResponse([
            'items' => [
                [
                    'url_from' => 'https://otrositio.com/post', 'domain_from' => 'otrositio.com',
                    'url_to' => 'https://ejemplo.com/pagina', 'anchor' => 'mejor tienda',
                    'dofollow' => true, 'first_seen' => '2025-01-15 00:00:00 +00:00',
                    'last_seen' => '2026-08-01 00:00:00 +00:00', 'is_lost' => false, 'domain_from_rank' => 300,
                ],
            ],
        ]), 200),
    ]);

    app(SyncBacklinkProfile::class)->execute($project);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'backlinks/summary/live') && $request->data()[0]['target'] === 'ejemplo.com');

    $summary = BacklinkSummary::query()->where('project_id', $project->id)->where('domain', 'ejemplo.com')->first();
    expect($summary)->not->toBeNull()
        ->and($summary->total_backlinks)->toBe(1200)
        ->and($summary->referring_domains)->toBe(80)
        ->and($summary->domain_rank)->toBe(450)
        ->and($summary->broken_backlinks)->toBe(5);

    $referringDomain = ReferringDomain::query()->where('project_id', $project->id)->first();
    expect($referringDomain->domain)->toBe('otrositio.com')
        ->and($referringDomain->backlinks_count)->toBe(12)
        ->and($referringDomain->first_seen)->toBe('2025-01-15')
        ->and($referringDomain->is_lost)->toBeFalse();

    $backlink = Backlink::query()->where('project_id', $project->id)->first();
    expect($backlink->source_url)->toBe('https://otrositio.com/post')
        ->and($backlink->source_domain)->toBe('otrositio.com')
        ->and($backlink->target_url)->toBe('https://ejemplo.com/pagina')
        ->and($backlink->dofollow)->toBeTrue()
        ->and($backlink->is_lost)->toBeFalse()
        ->and($backlink->domain_rank)->toBe(300)
        ->and($backlink->link_hash)->toBe(Backlink::hashFor('https://otrositio.com/post', 'https://ejemplo.com/pagina'));
});

test('un segundo sync del mismo enlace hace upsert en vez de duplicar', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/backlinks/summary/live' => Http::response(dataForSeoLiveResponse(['backlinks' => 1]), 200),
        '*/backlinks/referring_domains/live' => Http::response(dataForSeoLiveResponse(['items' => []]), 200),
        '*/backlinks/backlinks/live' => Http::response(dataForSeoLiveResponse([
            'items' => [[
                'url_from' => 'https://otrositio.com/post', 'domain_from' => 'otrositio.com',
                'url_to' => 'https://ejemplo.com/pagina', 'dofollow' => true, 'is_lost' => false,
            ]],
        ]), 200),
    ]);

    app(SyncBacklinkProfile::class)->execute($project);
    app(SyncBacklinkProfile::class)->execute($project);

    expect(Backlink::query()->where('project_id', $project->id)->count())->toBe(1);
});

test('marca is_lost segun lo que diga la api, sin inventarlo', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/backlinks/summary/live' => Http::response(dataForSeoLiveResponse(['backlinks' => 1]), 200),
        '*/backlinks/referring_domains/live' => Http::response(dataForSeoLiveResponse(['items' => []]), 200),
        '*/backlinks/backlinks/live' => Http::response(dataForSeoLiveResponse([
            'items' => [[
                'url_from' => 'https://otrositio.com/post', 'domain_from' => 'otrositio.com',
                'url_to' => 'https://ejemplo.com/pagina', 'dofollow' => true, 'is_lost' => true,
            ]],
        ]), 200),
    ]);

    app(SyncBacklinkProfile::class)->execute($project);

    expect(Backlink::query()->where('project_id', $project->id)->first()->is_lost)->toBeTrue();
});

test('propaga la excepcion de presupuesto sin sincronizar nada', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake();
    app(CircuitBreaker::class)->trip('prueba');

    expect(fn () => app(SyncBacklinkProfile::class)->execute($project))
        ->toThrow(DataForSeoBudgetExceededException::class);

    Http::assertNothingSent();
    expect(BacklinkSummary::query()->count())->toBe(0);
});
