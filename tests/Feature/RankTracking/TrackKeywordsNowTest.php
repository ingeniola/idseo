<?php

declare(strict_types=1);

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Jobs\TrackKeywordsNow;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\Models\Project;
use App\RankTracking\PostRankTrackingTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeTaskPostResponseFor(array $taskIds): array
{
    return [
        'version' => '0.1.20250101',
        'status_code' => 20000,
        'status_message' => 'Ok.',
        'time' => '0.2 sec.',
        'cost' => 0.003 * count($taskIds),
        'tasks_count' => count($taskIds),
        'tasks_error' => 0,
        'tasks' => array_map(fn (string $id) => [
            'id' => $id,
            'status_code' => 20000,
            'status_message' => 'Ok.',
            'time' => '0.1 sec.',
            'cost' => 0.003,
            'result_count' => 0,
            'path' => ['v3', 'serp', 'google', 'organic', 'task_post'],
            'data' => [],
            'result' => null,
        ], $taskIds),
    ];
}

test('postea task_post de inmediato para las keywords dadas, sin esperar al corte diario', function () {
    config(['rank_tracking.webhook_token' => 'test-token']);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true, 'keyword' => 'clinica dental']);

    Http::fake([
        '*/serp/google/organic/task_post' => Http::response(fakeTaskPostResponseFor(['task-now']), 200),
    ]);

    app(TrackKeywordsNow::class, ['keywordIds' => [$keyword->id]])->handle(app(PostRankTrackingTasks::class));

    Http::assertSentCount(1);

    $task = DataForSeoTask::query()->where('task_id', 'task-now')->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(DataForSeoTaskStatus::Pending)
        ->and($task->taskable_id)->toBe($keyword->id);
});

test('agrupa por proyecto si las keywords del lote pertenecen a proyectos distintos', function () {
    config(['rank_tracking.webhook_token' => 'test-token']);

    $projectA = Project::factory()->create();
    $keywordA = Keyword::factory()->create(['project_id' => $projectA->id, 'is_active' => true]);

    $projectB = Project::factory()->create();
    $keywordB = Keyword::factory()->create(['project_id' => $projectB->id, 'is_active' => true]);

    Http::fake([
        '*/serp/google/organic/task_post' => Http::sequence()
            ->push(fakeTaskPostResponseFor(['task-a']), 200)
            ->push(fakeTaskPostResponseFor(['task-b']), 200),
    ]);

    app(TrackKeywordsNow::class, ['keywordIds' => [$keywordA->id, $keywordB->id]])->handle(app(PostRankTrackingTasks::class));

    Http::assertSentCount(2);
    expect(DataForSeoTask::query()->count())->toBe(2);
});

test('ignora keywords inactivas', function () {
    config(['rank_tracking.webhook_token' => 'test-token']);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => false]);

    Http::fake();

    app(TrackKeywordsNow::class, ['keywordIds' => [$keyword->id]])->handle(app(PostRankTrackingTasks::class));

    Http::assertNothingSent();
    expect(DataForSeoTask::query()->count())->toBe(0);
});

test('no duplica el envio si la keyword ya tiene una tarea de rank tracking pendiente en vuelo', function () {
    config(['rank_tracking.webhook_token' => 'test-token']);

    $project = Project::factory()->create();
    $conPendiente = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    $sinPendiente = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    DataForSeoTask::factory()->create([
        'taskable_type' => Keyword::class,
        'taskable_id' => $conPendiente->id,
        'endpoint' => PostRankTrackingTasks::ENDPOINT,
        'status' => DataForSeoTaskStatus::Pending,
    ]);

    Http::fake([
        '*/serp/google/organic/task_post' => Http::response(fakeTaskPostResponseFor(['task-solo-sin-pendiente']), 200),
    ]);

    app(TrackKeywordsNow::class, ['keywordIds' => [$conPendiente->id, $sinPendiente->id]])->handle(app(PostRankTrackingTasks::class));

    Http::assertSentCount(1);

    $sentPayload = null;
    Http::assertSent(function (Request $request) use (&$sentPayload) {
        $sentPayload = $request->data();

        return true;
    });

    expect($sentPayload)->toHaveCount(1);
});
