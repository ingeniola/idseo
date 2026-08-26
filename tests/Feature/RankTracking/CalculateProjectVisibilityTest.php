<?php

declare(strict_types=1);

use App\Jobs\CalculateProjectVisibility;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\ProjectVisibilitySnapshot;
use App\Models\Ranking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function keywordWithLatestPosition(Project $project, ?int $position): Keyword
{
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    if ($position !== null) {
        Ranking::factory()->create([
            'keyword_id' => $keyword->id,
            'checked_at' => Carbon::yesterday()->toDateString(),
            'position' => $position,
        ]);
    }

    return $keyword;
}

test('calcula visibility_score ponderado y los conteos por rango de posicion', function () {
    $project = Project::factory()->create(['is_active' => true]);

    keywordWithLatestPosition($project, 1);  // top 3 -> peso 1.0
    keywordWithLatestPosition($project, 8);  // top 10 -> peso 0.5
    keywordWithLatestPosition($project, 15); // top 20 -> peso 0.2
    keywordWithLatestPosition($project, 50); // fuera -> peso 0.0

    app(CalculateProjectVisibility::class)->handle();

    $snapshot = ProjectVisibilitySnapshot::query()->where('project_id', $project->id)->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->tracked_keywords_count)->toBe(4)
        ->and($snapshot->keywords_in_top_3)->toBe(1)
        ->and($snapshot->keywords_in_top_10)->toBe(2)
        ->and($snapshot->keywords_in_top_20)->toBe(3)
        ->and((float) $snapshot->visibility_score)->toEqual(round(((1.0 + 0.5 + 0.2 + 0.0) / 4) * 100, 2))
        ->and((float) $snapshot->average_position)->toEqual(round((1 + 8 + 15 + 50) / 4, 2))
        ->and($snapshot->calculated_at)->toBe(Carbon::today()->toDateString());
});

test('ignora keywords sin ranking conocido y sin importar cual fue la ultima revisada', function () {
    $project = Project::factory()->create(['is_active' => true]);

    keywordWithLatestPosition($project, 2);
    keywordWithLatestPosition($project, null);

    app(CalculateProjectVisibility::class)->handle();

    $snapshot = ProjectVisibilitySnapshot::query()->where('project_id', $project->id)->first();

    expect($snapshot->tracked_keywords_count)->toBe(1);
});

test('con cero keywords rastreadas el score es 0 y average_position es null', function () {
    $project = Project::factory()->create(['is_active' => true]);
    keywordWithLatestPosition($project, null);

    app(CalculateProjectVisibility::class)->handle();

    $snapshot = ProjectVisibilitySnapshot::query()->where('project_id', $project->id)->first();

    expect($snapshot->tracked_keywords_count)->toBe(0)
        ->and((float) $snapshot->visibility_score)->toEqual(0.0)
        ->and($snapshot->average_position)->toBeNull();
});

test('ignora keywords inactivas y proyectos inactivos', function () {
    $activeProject = Project::factory()->create(['is_active' => true]);
    keywordWithLatestPosition($activeProject, 1);
    Keyword::factory()->create(['project_id' => $activeProject->id, 'is_active' => false]);

    $inactiveProject = Project::factory()->create(['is_active' => false]);
    keywordWithLatestPosition($inactiveProject, 1);

    app(CalculateProjectVisibility::class)->handle();

    expect(ProjectVisibilitySnapshot::query()->where('project_id', $activeProject->id)->first()->tracked_keywords_count)->toBe(1)
        ->and(ProjectVisibilitySnapshot::query()->where('project_id', $inactiveProject->id)->exists())->toBeFalse();
});

test('correr el job dos veces el mismo dia actualiza el snapshot en vez de duplicarlo', function () {
    $project = Project::factory()->create(['is_active' => true]);
    keywordWithLatestPosition($project, 1);

    app(CalculateProjectVisibility::class)->handle();
    keywordWithLatestPosition($project, 50);
    app(CalculateProjectVisibility::class)->handle();

    expect(ProjectVisibilitySnapshot::query()->where('project_id', $project->id)->count())->toBe(1)
        ->and(ProjectVisibilitySnapshot::query()->where('project_id', $project->id)->first()->tracked_keywords_count)->toBe(2);
});
