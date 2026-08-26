<?php

declare(strict_types=1);

use App\Jobs\CalculateSerpCompetitors;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\SerpCompetitor;
use App\Models\SerpSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * @param  array<int, array{domain: string, rank_group: int}>  $organicResults
 */
function keywordWithSerpSnapshot(Project $project, array $organicResults): Keyword
{
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    SerpSnapshot::query()->create([
        'keyword_id' => $keyword->id,
        'captured_at' => now(),
        'raw_response' => [],
        'top_results' => array_map(
            fn (array $result) => ['type' => 'organic', 'domain' => $result['domain'], 'rank_group' => $result['rank_group']],
            $organicResults,
        ),
    ]);

    return $keyword;
}

test('agrega dominios competidores por keywords_overlap, posicion promedio y visibility_score', function () {
    $project = Project::factory()->create(['is_active' => true, 'domain' => 'micliente.com']);

    keywordWithSerpSnapshot($project, [
        ['domain' => 'micliente.com', 'rank_group' => 1],
        ['domain' => 'competidor.com', 'rank_group' => 2],
        ['domain' => 'otro.com', 'rank_group' => 9],
    ]);
    keywordWithSerpSnapshot($project, [
        ['domain' => 'competidor.com', 'rank_group' => 4],
    ]);

    app(CalculateSerpCompetitors::class)->handle();

    $competitor = SerpCompetitor::query()->where('project_id', $project->id)->where('domain', 'competidor.com')->first();

    expect($competitor)->not->toBeNull()
        ->and($competitor->keywords_overlap)->toBe(2)
        ->and((float) $competitor->avg_position)->toEqual(round((2 + 4) / 2, 2))
        // peso(2) = 1.0, peso(4) = 0.5, sobre 2 keywords rastreadas: ((1.0+0.5)/2)*100
        ->and((float) $competitor->visibility_score)->toEqual(round(((1.0 + 0.5) / 2) * 100, 2))
        ->and($competitor->calculated_at)->toBe(Carbon::today()->toDateString());

    $other = SerpCompetitor::query()->where('project_id', $project->id)->where('domain', 'otro.com')->first();
    expect($other->keywords_overlap)->toBe(1);
});

test('excluye el propio dominio del proyecto, incluyendo subdominios', function () {
    $project = Project::factory()->create(['is_active' => true, 'domain' => 'micliente.com']);

    keywordWithSerpSnapshot($project, [
        ['domain' => 'micliente.com', 'rank_group' => 1],
        ['domain' => 'blog.micliente.com', 'rank_group' => 3],
        ['domain' => 'competidor.com', 'rank_group' => 5],
    ]);

    app(CalculateSerpCompetitors::class)->handle();

    expect(SerpCompetitor::query()->where('project_id', $project->id)->pluck('domain')->all())
        ->toBe(['competidor.com']);
});

test('ignora keywords sin snapshot y solo tipos organic', function () {
    $project = Project::factory()->create(['is_active' => true, 'domain' => 'micliente.com']);

    Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    SerpSnapshot::query()->create([
        'keyword_id' => $keyword->id,
        'captured_at' => now(),
        'raw_response' => [],
        'top_results' => [
            ['type' => 'featured_snippet', 'domain' => 'competidor.com', 'rank_group' => 1],
            ['type' => 'organic', 'domain' => 'competidor.com', 'rank_group' => 2],
        ],
    ]);

    app(CalculateSerpCompetitors::class)->handle();

    $competitor = SerpCompetitor::query()->where('project_id', $project->id)->first();
    expect($competitor->keywords_overlap)->toBe(1);
});

test('ignora keywords inactivas y proyectos inactivos', function () {
    $activeProject = Project::factory()->create(['is_active' => true, 'domain' => 'micliente.com']);
    keywordWithSerpSnapshot($activeProject, [['domain' => 'competidor.com', 'rank_group' => 1]]);
    $inactiveKeyword = Keyword::factory()->create(['project_id' => $activeProject->id, 'is_active' => false]);
    SerpSnapshot::query()->create([
        'keyword_id' => $inactiveKeyword->id,
        'captured_at' => now(),
        'raw_response' => [],
        'top_results' => [['type' => 'organic', 'domain' => 'ignorado.com', 'rank_group' => 1]],
    ]);

    $inactiveProject = Project::factory()->create(['is_active' => false, 'domain' => 'otro.com']);
    keywordWithSerpSnapshot($inactiveProject, [['domain' => 'competidor.com', 'rank_group' => 1]]);

    app(CalculateSerpCompetitors::class)->handle();

    expect(SerpCompetitor::query()->where('domain', 'ignorado.com')->exists())->toBeFalse()
        ->and(SerpCompetitor::query()->where('project_id', $inactiveProject->id)->exists())->toBeFalse();
});

test('correr el job dos veces el mismo dia actualiza en vez de duplicar', function () {
    $project = Project::factory()->create(['is_active' => true, 'domain' => 'micliente.com']);
    keywordWithSerpSnapshot($project, [['domain' => 'competidor.com', 'rank_group' => 10]]);

    app(CalculateSerpCompetitors::class)->handle();

    $keyword = Keyword::query()->where('project_id', $project->id)->first();
    $keyword->serpSnapshots()->delete();
    SerpSnapshot::query()->create([
        'keyword_id' => $keyword->id,
        'captured_at' => now(),
        'raw_response' => [],
        'top_results' => [['type' => 'organic', 'domain' => 'competidor.com', 'rank_group' => 1]],
    ]);

    app(CalculateSerpCompetitors::class)->handle();

    $rows = SerpCompetitor::query()->where('project_id', $project->id)->where('domain', 'competidor.com')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->avg_position)->toEqual('1.00');
});

test('un dominio que ya no aparece hoy se elimina de la fecha de hoy', function () {
    $project = Project::factory()->create(['is_active' => true, 'domain' => 'micliente.com']);
    $keyword = keywordWithSerpSnapshot($project, [
        ['domain' => 'competidor.com', 'rank_group' => 1],
        ['domain' => 'desaparece.com', 'rank_group' => 5],
    ]);

    app(CalculateSerpCompetitors::class)->handle();
    expect(SerpCompetitor::query()->where('project_id', $project->id)->count())->toBe(2);

    $keyword->serpSnapshots()->delete();
    SerpSnapshot::query()->create([
        'keyword_id' => $keyword->id,
        'captured_at' => now(),
        'raw_response' => [],
        'top_results' => [['type' => 'organic', 'domain' => 'competidor.com', 'rank_group' => 1]],
    ]);

    app(CalculateSerpCompetitors::class)->handle();

    expect(SerpCompetitor::query()->where('project_id', $project->id)->pluck('domain')->all())
        ->toBe(['competidor.com']);
});
