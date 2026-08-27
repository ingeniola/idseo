<?php

declare(strict_types=1);

use App\DataForSeo\Backlinks\CompareCompetitorBacklinks;
use App\Models\BacklinkSummary;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('guarda el resumen de un dominio competidor sin tocar el perfil propio', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);
    BacklinkSummary::factory()->create(['project_id' => $project->id, 'domain' => 'ejemplo.com']);

    Http::fake([
        '*/backlinks/summary/live' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.01, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
                'cost' => 0.01, 'result_count' => 1, 'path' => [], 'data' => [],
                'result' => [[
                    'target' => 'competidor.com', 'rank' => 700, 'backlinks' => 5000,
                    'referring_domains' => 300, 'referring_ips' => 200, 'broken_backlinks' => 10,
                ]],
            ]],
        ], 200),
    ]);

    $summary = app(CompareCompetitorBacklinks::class)->execute($project, 'competidor.com');

    Http::assertSent(fn (Request $request) => $request->data()[0]['target'] === 'competidor.com');

    expect($summary->domain)->toBe('competidor.com')
        ->and($summary->total_backlinks)->toBe(5000)
        ->and($summary->domain_rank)->toBe(700);

    expect(BacklinkSummary::query()->where('project_id', $project->id)->count())->toBe(2);
});
