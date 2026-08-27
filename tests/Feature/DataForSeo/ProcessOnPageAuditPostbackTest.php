<?php

declare(strict_types=1);

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Jobs\ProcessOnPageAuditPostback;
use App\Models\AuditIssue;
use App\Models\DataForSeoTask;
use App\Models\Project;
use App\Models\SiteAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function onPagePostbackPayload(string $taskId = 'task-onpage-1', int $statusCode = 20000): array
{
    return [
        'tasks' => [[
            'id' => $taskId, 'status_code' => $statusCode,
            'status_message' => $statusCode === 20000 ? 'Ok.' : 'Error.',
        ]],
    ];
}

function pendingOnPageTask(SiteAudit $audit, string $taskId = 'task-onpage-1'): DataForSeoTask
{
    return DataForSeoTask::factory()->create([
        'task_id' => $taskId,
        'endpoint' => 'on_page/task_post',
        'taskable_type' => SiteAudit::class,
        'taskable_id' => $audit->id,
        'status' => DataForSeoTaskStatus::Pending,
        'payload_received' => json_encode(onPagePostbackPayload($taskId)),
    ]);
}

function onPageSummaryResponse(int $pagesCrawled, float $onpageScore, float $cost = 0.05): array
{
    return [
        'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
        'cost' => $cost, 'tasks_count' => 1, 'tasks_error' => 0,
        'tasks' => [[
            'id' => 'task-onpage-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => $cost, 'result_count' => 1, 'path' => [], 'data' => [],
            'result' => [[
                'crawl_progress' => 'finished',
                'crawl_status' => ['pages_crawled' => $pagesCrawled, 'pages_in_queue' => 0],
                'onpage_score' => $onpageScore,
            ]],
        ]],
    ];
}

function onPagePagesResponse(array $items): array
{
    return [
        'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
        'cost' => 0.0, 'tasks_count' => 1, 'tasks_error' => 0,
        'tasks' => [[
            'id' => 'task-onpage-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.0, 'result_count' => 1, 'path' => [], 'data' => [],
            'result' => [['items' => $items]],
        ]],
    ];
}

test('completa el audit y guarda issues a partir de los checks verdaderos', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);
    $audit = SiteAudit::factory()->create(['project_id' => $project->id, 'status' => DataForSeoTaskStatus::Pending, 'task_id' => 'task-onpage-1']);
    $task = pendingOnPageTask($audit);

    Http::fake([
        '*/on_page/summary/*' => Http::response(onPageSummaryResponse(12, 78.5), 200),
        '*/on_page/pages' => Http::response(onPagePagesResponse([
            [
                'url' => 'https://ejemplo.com/pagina-rota',
                'checks' => ['is_broken' => true, 'is_4xx_code' => true, 'is_https' => true, 'is_www' => false],
            ],
            [
                'url' => 'https://ejemplo.com/pagina-lenta',
                'checks' => ['high_loading_time' => true],
            ],
        ]), 200),
    ]);

    (new ProcessOnPageAuditPostback($task->id))->handle(app(DataForSeoClient::class));

    $audit->refresh();
    expect($audit->status)->toBe(DataForSeoTaskStatus::Completed)
        ->and($audit->pages_crawled)->toBe(12)
        ->and((float) $audit->onpage_score)->toEqual(78.5)
        ->and($task->fresh()->status)->toBe(DataForSeoTaskStatus::Completed);

    $issues = AuditIssue::query()->where('audit_id', $audit->id)->get();
    expect($issues)->toHaveCount(3);

    $brokenPageIssues = $issues->where('url', 'https://ejemplo.com/pagina-rota')->pluck('issue_type')->map(fn ($type) => $type->value)->all();
    expect($brokenPageIssues)->toContain('is_broken')
        ->and($brokenPageIssues)->toContain('is_4xx_code')
        // is_https / is_www son hechos descriptivos, no "true = problema": nunca generan issue.
        ->and($brokenPageIssues)->not->toContain('is_https')
        ->and($brokenPageIssues)->not->toContain('is_www');

    $slowPageIssues = $issues->where('url', 'https://ejemplo.com/pagina-lenta')->pluck('issue_type')->map(fn ($type) => $type->value)->all();
    expect($slowPageIssues)->toBe(['high_loading_time']);
});

test('marca failed si el status_code del postback no es exitoso, sin llamar a summary ni pages', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);
    $audit = SiteAudit::factory()->create(['project_id' => $project->id, 'status' => DataForSeoTaskStatus::Pending, 'task_id' => 'task-onpage-1']);
    $task = DataForSeoTask::factory()->create([
        'task_id' => 'task-onpage-1',
        'endpoint' => 'on_page/task_post',
        'taskable_type' => SiteAudit::class,
        'taskable_id' => $audit->id,
        'status' => DataForSeoTaskStatus::Pending,
        'payload_received' => json_encode(onPagePostbackPayload(statusCode: 40501)),
    ]);

    Http::fake();

    (new ProcessOnPageAuditPostback($task->id))->handle(app(DataForSeoClient::class));

    expect($audit->fresh()->status)->toBe(DataForSeoTaskStatus::Failed)
        ->and($task->fresh()->status)->toBe(DataForSeoTaskStatus::Failed);
    Http::assertNothingSent();
});

test('no hace nada si la tarea ya no esta pending', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com']);
    $audit = SiteAudit::factory()->create(['project_id' => $project->id, 'status' => DataForSeoTaskStatus::Completed, 'task_id' => 'task-onpage-1']);
    $task = pendingOnPageTask($audit);
    $task->update(['status' => DataForSeoTaskStatus::Completed]);

    Http::fake();

    (new ProcessOnPageAuditPostback($task->id))->handle(app(DataForSeoClient::class));

    Http::assertNothingSent();
});

test('registra error si la tarea no corresponde a un SiteAudit', function () {
    $project = Project::factory()->create();

    $task = DataForSeoTask::factory()->create([
        'task_id' => 'task-otro-tipo',
        'taskable_type' => Project::class,
        'taskable_id' => $project->id,
        'status' => DataForSeoTaskStatus::Pending,
        'payload_received' => json_encode(onPagePostbackPayload('task-otro-tipo')),
    ]);

    Http::fake();

    (new ProcessOnPageAuditPostback($task->id))->handle(app(DataForSeoClient::class));

    expect($task->fresh()->status)->toBe(DataForSeoTaskStatus::Pending);
    Http::assertNothingSent();
});
