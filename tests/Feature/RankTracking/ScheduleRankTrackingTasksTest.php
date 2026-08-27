<?php

declare(strict_types=1);

use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Jobs\ScheduleRankTrackingTasks;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\Models\Project;
use App\RankTracking\DueKeywordsFinder;
use App\RankTracking\PostRankTrackingTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function fakeTaskPostResponse(array $taskIds, int $statusCode = 20000): array
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
            'status_code' => $statusCode,
            'status_message' => $statusCode === 20000 ? 'Ok.' : 'Error interno inesperado.',
            'time' => '0.1 sec.',
            'cost' => 0.003,
            'result_count' => 0,
            'path' => ['v3', 'serp', 'google', 'organic', 'task_post'],
            'data' => [],
            'result' => null,
        ], $taskIds),
    ];
}

test('no programa nada si falta el webhook token', function () {
    config(['rank_tracking.webhook_token' => '']);
    Log::spy();

    $project = Project::factory()->create();
    Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    Http::fake();

    app(ScheduleRankTrackingTasks::class)->handle(app(PostRankTrackingTasks::class), app(DueKeywordsFinder::class));

    Http::assertNothingSent();
    Log::shouldHaveReceived('error')->once();
    expect(DataForSeoTask::query()->count())->toBe(0);
});

test('crea una DataForSeoTask pending por keyword due al postear correctamente', function () {
    config(['rank_tracking.webhook_token' => 'test-token']);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true, 'keyword' => 'zapatos', 'location_code' => 2340, 'language_code' => 'es']);

    Http::fake([
        '*/serp/google/organic/task_post' => Http::response(fakeTaskPostResponse(['task-abc']), 200),
    ]);

    app(ScheduleRankTrackingTasks::class)->handle(app(PostRankTrackingTasks::class), app(DueKeywordsFinder::class));

    Http::assertSentCount(1);

    $sentPayload = null;
    Http::assertSent(function (Request $request) use (&$sentPayload) {
        $sentPayload = $request->data();

        return true;
    });

    expect($sentPayload)->toHaveCount(1)
        ->and($sentPayload[0]['keyword'])->toBe('zapatos')
        ->and($sentPayload[0]['postback_url'])->toContain('token=test-token')
        ->and($sentPayload[0]['postback_data'])->toBe(config('rank_tracking.postback_data'));

    $task = DataForSeoTask::query()->where('task_id', 'task-abc')->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(DataForSeoTaskStatus::Pending)
        ->and($task->taskable_type)->toBe(Keyword::class)
        ->and($task->taskable_id)->toBe($keyword->id)
        ->and($task->endpoint)->toBe('serp/google/organic/task_post');
});

test('marca la tarea como failed cuando el status_code de la tarea individual no es exitoso', function () {
    config(['rank_tracking.webhook_token' => 'test-token']);

    $project = Project::factory()->create();
    Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    Http::fake([
        '*/serp/google/organic/task_post' => Http::response(fakeTaskPostResponse(['task-fail'], 40501), 200),
    ]);

    app(ScheduleRankTrackingTasks::class)->handle(app(PostRankTrackingTasks::class), app(DueKeywordsFinder::class));

    $task = DataForSeoTask::query()->where('task_id', 'task-fail')->first();

    expect($task->status)->toBe(DataForSeoTaskStatus::Failed)
        ->and($task->error_message)->not->toBeNull();
});

test('divide el lote en varios requests segun max_tasks_per_request', function () {
    config(['rank_tracking.webhook_token' => 'test-token']);
    config(['dataforseo.max_tasks_per_request' => 1]);

    $project = Project::factory()->create();
    Keyword::factory()->count(2)->create(['project_id' => $project->id, 'is_active' => true]);

    Http::fake([
        '*/serp/google/organic/task_post' => Http::sequence()
            ->push(fakeTaskPostResponse(['task-1']), 200)
            ->push(fakeTaskPostResponse(['task-2']), 200),
    ]);

    app(ScheduleRankTrackingTasks::class)->handle(app(PostRankTrackingTasks::class), app(DueKeywordsFinder::class));

    Http::assertSentCount(2);
    expect(DataForSeoTask::query()->count())->toBe(2);
});

test('con el circuit breaker de presupuesto activo el job no lanza excepcion y no postea nada', function () {
    config(['rank_tracking.webhook_token' => 'test-token']);

    $projectA = Project::factory()->create();
    Keyword::factory()->create(['project_id' => $projectA->id, 'is_active' => true]);

    $projectB = Project::factory()->create();
    Keyword::factory()->create(['project_id' => $projectB->id, 'is_active' => true]);

    app(CircuitBreaker::class)->trip('prueba de presupuesto excedido');

    Http::fake();

    // DataForSeoBudgetExceededException se captura dentro de
    // PostRankTrackingTasks::postChunk() por proyecto: el job debe
    // recorrer ambos proyectos sin propagar la excepción.
    app(ScheduleRankTrackingTasks::class)->handle(app(PostRankTrackingTasks::class), app(DueKeywordsFinder::class));

    Http::assertNothingSent();
    expect(DataForSeoTask::query()->count())->toBe(0);
});
