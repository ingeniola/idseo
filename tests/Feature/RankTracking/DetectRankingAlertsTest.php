<?php

declare(strict_types=1);

use App\Enums\AlertType;
use App\Jobs\DetectRankingAlerts;
use App\Jobs\SendRankingAlertDigest;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\Ranking;
use App\Models\RankingAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

/**
 * @param  array{position: int|null, is_featured_snippet?: bool}  $previous
 * @param  array{position: int|null, is_featured_snippet?: bool}  $current
 */
function keywordWithRankingHistory(Project $project, array $previous, array $current): Keyword
{
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    Ranking::factory()->create([
        'keyword_id' => $keyword->id,
        'checked_at' => Carbon::yesterday()->toDateString(),
        'position' => $previous['position'],
        'is_featured_snippet' => $previous['is_featured_snippet'] ?? false,
    ]);
    Ranking::factory()->create([
        'keyword_id' => $keyword->id,
        'checked_at' => Carbon::today()->toDateString(),
        'position' => $current['position'],
        'is_featured_snippet' => $current['is_featured_snippet'] ?? false,
    ]);

    return $keyword;
}

beforeEach(function () {
    config(['alerts.position_drop_threshold' => 5]);
});

test('detecta caida de posiciones por encima del umbral', function () {
    Bus::fake();
    $project = Project::factory()->create(['is_active' => true]);
    $keyword = keywordWithRankingHistory($project, ['position' => 3], ['position' => 9]);

    app(DetectRankingAlerts::class)->handle();

    $alert = RankingAlert::query()->where('keyword_id', $keyword->id)->where('type', AlertType::PositionDrop)->first();
    expect($alert)->not->toBeNull()
        ->and($alert->previous_position)->toBe(3)
        ->and($alert->current_position)->toBe(9)
        ->and($alert->project_id)->toBe($project->id);
});

test('una caida menor al umbral no genera alerta', function () {
    Bus::fake();
    $project = Project::factory()->create(['is_active' => true]);
    keywordWithRankingHistory($project, ['position' => 3], ['position' => 6]);

    app(DetectRankingAlerts::class)->handle();

    expect(RankingAlert::query()->where('type', AlertType::PositionDrop)->exists())->toBeFalse();
});

test('detecta salida del top 10', function () {
    Bus::fake();
    $project = Project::factory()->create(['is_active' => true]);
    keywordWithRankingHistory($project, ['position' => 8], ['position' => 11]);

    app(DetectRankingAlerts::class)->handle();

    expect(RankingAlert::query()->where('type', AlertType::ExitedTop10)->exists())->toBeTrue();
});

test('salir del top 10 hacia sin ranking tambien cuenta como salida del top 10', function () {
    Bus::fake();
    $project = Project::factory()->create(['is_active' => true]);
    keywordWithRankingHistory($project, ['position' => 5], ['position' => null]);

    app(DetectRankingAlerts::class)->handle();

    expect(RankingAlert::query()->where('type', AlertType::ExitedTop10)->exists())->toBeTrue();
});

test('detecta entrada al top 3', function () {
    Bus::fake();
    $project = Project::factory()->create(['is_active' => true]);
    keywordWithRankingHistory($project, ['position' => 5], ['position' => 2]);

    app(DetectRankingAlerts::class)->handle();

    expect(RankingAlert::query()->where('type', AlertType::EnteredTop3)->exists())->toBeTrue();
});

test('detecta perdida de featured snippet', function () {
    Bus::fake();
    $project = Project::factory()->create(['is_active' => true]);
    keywordWithRankingHistory(
        $project,
        ['position' => 1, 'is_featured_snippet' => true],
        ['position' => 1, 'is_featured_snippet' => false],
    );

    app(DetectRankingAlerts::class)->handle();

    expect(RankingAlert::query()->where('type', AlertType::LostFeaturedSnippet)->exists())->toBeTrue();
});

test('sin dos rankings todavia no genera ninguna alerta', function () {
    Bus::fake();
    $project = Project::factory()->create(['is_active' => true]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => Carbon::today()->toDateString(), 'position' => 1]);

    app(DetectRankingAlerts::class)->handle();

    expect(RankingAlert::query()->count())->toBe(0);
});

test('ignora keywords inactivas y proyectos inactivos', function () {
    Bus::fake();
    $activeProject = Project::factory()->create(['is_active' => true]);
    $inactiveKeyword = Keyword::factory()->create(['project_id' => $activeProject->id, 'is_active' => false]);
    Ranking::factory()->create(['keyword_id' => $inactiveKeyword->id, 'checked_at' => Carbon::yesterday()->toDateString(), 'position' => 3]);
    Ranking::factory()->create(['keyword_id' => $inactiveKeyword->id, 'checked_at' => Carbon::today()->toDateString(), 'position' => 20]);

    $inactiveProject = Project::factory()->create(['is_active' => false]);
    keywordWithRankingHistory($inactiveProject, ['position' => 3], ['position' => 20]);

    app(DetectRankingAlerts::class)->handle();

    expect(RankingAlert::query()->count())->toBe(0);
});

test('correr el job dos veces el mismo dia no duplica ni renotifica', function () {
    Bus::fake();
    $project = Project::factory()->create(['is_active' => true]);
    keywordWithRankingHistory($project, ['position' => 3], ['position' => 9]);

    app(DetectRankingAlerts::class)->handle();
    app(DetectRankingAlerts::class)->handle();

    expect(RankingAlert::query()->where('type', AlertType::PositionDrop)->count())->toBe(1);
    Bus::assertDispatchedTimes(SendRankingAlertDigest::class, 1);
});

test('despacha el resumen solo cuando hay alertas nuevas', function () {
    Bus::fake();
    $project = Project::factory()->create(['is_active' => true]);
    keywordWithRankingHistory($project, ['position' => 3], ['position' => 4]);

    app(DetectRankingAlerts::class)->handle();

    Bus::assertNotDispatched(SendRankingAlertDigest::class);
});
