<?php

declare(strict_types=1);

use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\DataForSeo\OnPage\TriggerSiteAudit;
use App\Models\CostLedger;
use App\Models\DataForSeoTask;
use App\Models\Project;
use App\Models\SiteAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function onPageTaskPostResponse(string $taskId = 'task-onpage-1', int $statusCode = 20000): array
{
    return [
        'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
        'cost' => 0.0, 'tasks_count' => 1, 'tasks_error' => 0,
        'tasks' => [[
            'id' => $taskId, 'status_code' => $statusCode, 'status_message' => $statusCode === 20000 ? 'Ok.' : 'Error.',
            'time' => '0.1 sec.', 'cost' => 0.0, 'result_count' => 0, 'path' => [], 'data' => [], 'result' => null,
        ]],
    ];
}

beforeEach(function () {
    config(['rank_tracking.webhook_token' => 'test-token']);
});

test('crea el site audit y la tarea polimorfica al postear on_page/task_post', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/on_page/task_post' => Http::response(onPageTaskPostResponse(), 200),
    ]);

    $audit = app(TriggerSiteAudit::class)->execute($project, 50);

    expect($audit)->toBeInstanceOf(SiteAudit::class)
        ->and($audit->project_id)->toBe($project->id)
        ->and($audit->task_id)->toBe('task-onpage-1')
        ->and($audit->status)->toBe(DataForSeoTaskStatus::Pending)
        ->and($audit->started_at)->not->toBeNull();

    Http::assertSent(function (Request $request) use ($project) {
        $task = $request->data()[0];

        return str_contains($request->url(), 'on_page/task_post')
            && $task['target'] === $project->domain
            && $task['max_crawl_pages'] === 50
            && str_contains($task['postback_url'], '/webhooks/dataforseo/onpage?token=test-token');
    });

    $dataForSeoTask = DataForSeoTask::query()->where('task_id', 'task-onpage-1')->first();
    expect($dataForSeoTask)->not->toBeNull()
        ->and($dataForSeoTask->taskable_type)->toBe(SiteAudit::class)
        ->and($dataForSeoTask->taskable_id)->toBe($audit->id)
        ->and($dataForSeoTask->endpoint)->toBe('on_page/task_post');
});

test('la llamada a task_post queda atribuida al cliente y proyecto en cost_ledger', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/on_page/task_post' => Http::response(onPageTaskPostResponse(), 200),
    ]);

    app(TriggerSiteAudit::class)->execute($project, 50);

    $entry = CostLedger::query()->where('project_id', $project->id)->first();
    expect($entry)->not->toBeNull()
        ->and($entry->client_id)->toBe($project->client_id);
});

test('marca el audit como failed si dataforseo rechaza la tarea', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/on_page/task_post' => Http::response(onPageTaskPostResponse(statusCode: 40501), 200),
    ]);

    $audit = app(TriggerSiteAudit::class)->execute($project, 50);

    expect($audit->status)->toBe(DataForSeoTaskStatus::Failed);
});

test('propaga la excepcion de presupuesto sin crear nada', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake();
    app(CircuitBreaker::class)->trip('prueba');

    expect(fn () => app(TriggerSiteAudit::class)->execute($project, 50))
        ->toThrow(DataForSeoBudgetExceededException::class);

    Http::assertNothingSent();
    expect(SiteAudit::query()->count())->toBe(0);
});
