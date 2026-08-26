<?php

declare(strict_types=1);

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Jobs\ProcessDataForSeoPostback;
use App\Jobs\ReconcilePendingTasks;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sleep::fake();
});

function readyEnvelope(array $readyTaskIds): array
{
    return [
        'version' => '0.1.20250101',
        'status_code' => 20000,
        'status_message' => 'Ok.',
        'time' => '0.1 sec.',
        'cost' => 0,
        'tasks_count' => 1,
        'tasks_error' => 0,
        'tasks' => [[
            'id' => 'tasks-ready-envelope',
            'status_code' => 20000,
            'status_message' => 'Ok.',
            'time' => '0.1 sec.',
            'cost' => 0,
            'result_count' => count($readyTaskIds),
            'path' => ['v3', 'serp', 'google', 'organic', 'tasks_ready'],
            'data' => [],
            'result' => array_map(fn (string $id) => ['id' => $id], $readyTaskIds),
        ]],
    ];
}

function taskGetEnvelope(string $taskId): array
{
    return [
        'version' => '0.1.20250101',
        'status_code' => 20000,
        'status_message' => 'Ok.',
        'time' => '0.1 sec.',
        'cost' => 0.0025,
        'tasks_count' => 1,
        'tasks_error' => 0,
        'tasks' => [[
            'id' => $taskId,
            'status_code' => 20000,
            'status_message' => 'Ok.',
            'time' => '0.1 sec.',
            'cost' => 0.0025,
            'result_count' => 1,
            'path' => ['v3', 'serp', 'google', 'organic', 'task_get', 'advanced'],
            'data' => [],
            'result' => [['items' => []]],
        ]],
    ];
}

test('no llama a la API si no hay tareas huerfanas mas viejas que el umbral', function () {
    Http::fake();

    app(ReconcilePendingTasks::class)->handle(app(DataForSeoClient::class));

    Http::assertNothingSent();
});

test('recupera una tarea huerfana via tasks_ready + task_get y despacha el procesamiento', function () {
    Bus::fake();

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    $task = DataForSeoTask::factory()->create([
        'task_id' => 'task-huerfana',
        'endpoint' => 'serp/google/organic/task_post',
        'taskable_type' => Keyword::class,
        'taskable_id' => $keyword->id,
        'status' => DataForSeoTaskStatus::Pending,
        'posted_at' => now()->subHours(10),
    ]);

    Http::fake([
        '*/serp/google/organic/tasks_ready' => Http::response(readyEnvelope(['task-huerfana']), 200),
        '*/serp/google/organic/task_get/advanced/task-huerfana' => Http::response(taskGetEnvelope('task-huerfana'), 200),
    ]);

    app(ReconcilePendingTasks::class)->handle(app(DataForSeoClient::class));

    Http::assertSentCount(2);

    $stored = json_decode($task->fresh()->payload_received, true);
    expect($stored['tasks'][0]['id'])->toBe('task-huerfana')
        ->and($stored['tasks'][0]['status_code'])->toBe(20000);

    Bus::assertDispatched(ProcessDataForSeoPostback::class, fn (ProcessDataForSeoPostback $job) => $job->dataForSeoTaskId === $task->id);
});

test('ignora tareas listas que no son nuestras ni estan huerfanas', function () {
    Bus::fake();

    Http::fake([
        '*/serp/google/organic/tasks_ready' => Http::response(readyEnvelope(['task-de-otra-cuenta']), 200),
    ]);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    DataForSeoTask::factory()->create([
        'task_id' => 'task-nuestra-reciente',
        'endpoint' => 'serp/google/organic/task_post',
        'taskable_type' => Keyword::class,
        'taskable_id' => $keyword->id,
        'status' => DataForSeoTaskStatus::Pending,
        'posted_at' => now()->subHours(10),
    ]);

    app(ReconcilePendingTasks::class)->handle(app(DataForSeoClient::class));

    Http::assertSentCount(1);
    Bus::assertNotDispatched(ProcessDataForSeoPostback::class);
});

test('no considera huerfana una tarea pendiente reciente dentro del umbral', function () {
    config(['rank_tracking.reconcile_pending_after_hours' => 6]);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    DataForSeoTask::factory()->create([
        'task_id' => 'task-reciente',
        'endpoint' => 'serp/google/organic/task_post',
        'taskable_type' => Keyword::class,
        'taskable_id' => $keyword->id,
        'status' => DataForSeoTaskStatus::Pending,
        'posted_at' => now()->subHours(2),
    ]);

    Http::fake();

    app(ReconcilePendingTasks::class)->handle(app(DataForSeoClient::class));

    Http::assertNothingSent();
});

test('un fallo en task_get se registra y no interrumpe la reconciliacion', function () {
    Log::spy();
    Bus::fake();

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $task = DataForSeoTask::factory()->create([
        'task_id' => 'task-que-falla',
        'endpoint' => 'serp/google/organic/task_post',
        'taskable_type' => Keyword::class,
        'taskable_id' => $keyword->id,
        'status' => DataForSeoTaskStatus::Pending,
        'posted_at' => now()->subHours(10),
    ]);

    Http::fake([
        '*/serp/google/organic/tasks_ready' => Http::response(readyEnvelope(['task-que-falla']), 200),
        '*/serp/google/organic/task_get/advanced/task-que-falla' => Http::response([], 500),
    ]);

    app(ReconcilePendingTasks::class)->handle(app(DataForSeoClient::class));

    Log::shouldHaveReceived('warning')->once();
    Bus::assertNotDispatched(ProcessDataForSeoPostback::class);
    expect($task->fresh()->status)->toBe(DataForSeoTaskStatus::Pending);
});
