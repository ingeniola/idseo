<?php

declare(strict_types=1);

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Enums\TrackingFrequency;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\Ranking;
use App\RankTracking\DueKeywordsFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('incluye keywords sin ranking previo sin importar la frecuencia', function () {
    $project = Project::factory()->create(['tracking_frequency' => TrackingFrequency::Monthly]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    $due = app(DueKeywordsFinder::class)->forProject($project);

    expect($due->pluck('id'))->toContain($keyword->id);
});

test('excluye keywords inactivas', function () {
    $project = Project::factory()->create();
    Keyword::factory()->create(['project_id' => $project->id, 'is_active' => false]);

    $due = app(DueKeywordsFinder::class)->forProject($project);

    expect($due)->toHaveCount(0);
});

test('respeta la frecuencia diaria excluyendo lo revisado hoy', function () {
    $project = Project::factory()->create(['tracking_frequency' => TrackingFrequency::Daily]);
    $checkedToday = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    $checkedYesterday = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    Ranking::factory()->create(['keyword_id' => $checkedToday->id, 'checked_at' => Carbon::today()->toDateString()]);
    Ranking::factory()->create(['keyword_id' => $checkedYesterday->id, 'checked_at' => Carbon::yesterday()->toDateString()]);

    $due = app(DueKeywordsFinder::class)->forProject($project)->pluck('id');

    expect($due)->not->toContain($checkedToday->id)
        ->and($due)->toContain($checkedYesterday->id);
});

test('respeta la frecuencia semanal excluyendo lo revisado hace menos de 7 dias', function () {
    $project = Project::factory()->create(['tracking_frequency' => TrackingFrequency::Weekly]);
    $checkedRecently = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    $checkedLongAgo = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    Ranking::factory()->create(['keyword_id' => $checkedRecently->id, 'checked_at' => Carbon::today()->subDays(3)->toDateString()]);
    Ranking::factory()->create(['keyword_id' => $checkedLongAgo->id, 'checked_at' => Carbon::today()->subDays(7)->toDateString()]);

    $due = app(DueKeywordsFinder::class)->forProject($project)->pluck('id');

    expect($due)->not->toContain($checkedRecently->id)
        ->and($due)->toContain($checkedLongAgo->id);
});

test('respeta la frecuencia mensual usando 30 dias', function () {
    $project = Project::factory()->create(['tracking_frequency' => TrackingFrequency::Monthly]);
    $checkedRecently = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    $checkedLongAgo = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    Ranking::factory()->create(['keyword_id' => $checkedRecently->id, 'checked_at' => Carbon::today()->subDays(10)->toDateString()]);
    Ranking::factory()->create(['keyword_id' => $checkedLongAgo->id, 'checked_at' => Carbon::today()->subDays(30)->toDateString()]);

    $due = app(DueKeywordsFinder::class)->forProject($project)->pluck('id');

    expect($due)->not->toContain($checkedRecently->id)
        ->and($due)->toContain($checkedLongAgo->id);
});

test('excluye keywords que ya tienen una tarea pendiente en vuelo', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    DataForSeoTask::factory()->create([
        'taskable_type' => Keyword::class,
        'taskable_id' => $keyword->id,
        'status' => DataForSeoTaskStatus::Pending,
    ]);

    $due = app(DueKeywordsFinder::class)->forProject($project);

    expect($due)->toHaveCount(0);
});

test('no excluye keywords cuya tarea anterior ya se completo', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    DataForSeoTask::factory()->create([
        'taskable_type' => Keyword::class,
        'taskable_id' => $keyword->id,
        'status' => DataForSeoTaskStatus::Completed,
    ]);

    $due = app(DueKeywordsFinder::class)->forProject($project);

    expect($due->pluck('id'))->toContain($keyword->id);
});

test('no mezcla keywords de otro proyecto', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    Keyword::factory()->create(['project_id' => $projectB->id, 'is_active' => true]);

    $due = app(DueKeywordsFinder::class)->forProject($projectA);

    expect($due)->toHaveCount(0);
});
