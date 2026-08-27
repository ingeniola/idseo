<?php

declare(strict_types=1);

use App\DataForSeo\BusinessData\SyncBusinessReviews;
use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\Models\DataForSeoTask;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function businessReviewsTaskPostResponse(string $taskId = 'task-reviews-1', int $statusCode = 20000): array
{
    return [
        'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
        'cost' => 0.01, 'tasks_count' => 1, 'tasks_error' => 0,
        'tasks' => [[
            'id' => $taskId, 'status_code' => $statusCode, 'status_message' => $statusCode === 20000 ? 'Ok.' : 'Error.',
            'time' => '0.1 sec.', 'cost' => 0.01, 'result_count' => 0, 'path' => [], 'data' => [], 'result' => null,
        ]],
    ];
}

beforeEach(function () {
    config(['rank_tracking.webhook_token' => 'test-token']);
});

test('crea la tarea polimorfica sobre el project al postear business_data/google/reviews/task_post', function () {
    $project = Project::factory()->create(['google_business_place_id' => 'ChIJ12345']);

    Http::fake([
        '*/business_data/google/reviews/task_post' => Http::response(businessReviewsTaskPostResponse(), 200),
    ]);

    app(SyncBusinessReviews::class)->execute($project, 20);

    Http::assertSent(function (Request $request) use ($project) {
        $task = $request->data()[0];

        return str_contains($request->url(), 'business_data/google/reviews/task_post')
            && $task['place_id'] === $project->google_business_place_id
            && $task['depth'] === 20
            && str_contains($task['postback_url'], '/webhooks/dataforseo/reviews?token=test-token');
    });

    $dataForSeoTask = DataForSeoTask::query()->where('task_id', 'task-reviews-1')->first();
    expect($dataForSeoTask)->not->toBeNull()
        ->and($dataForSeoTask->taskable_type)->toBe(Project::class)
        ->and($dataForSeoTask->taskable_id)->toBe($project->id)
        ->and($dataForSeoTask->status)->toBe(DataForSeoTaskStatus::Pending);
});

test('marca la tarea como failed si dataforseo rechaza la tarea', function () {
    $project = Project::factory()->create(['google_business_place_id' => 'ChIJ12345']);

    Http::fake([
        '*/business_data/google/reviews/task_post' => Http::response(businessReviewsTaskPostResponse(statusCode: 40501), 200),
    ]);

    app(SyncBusinessReviews::class)->execute($project, 20);

    $dataForSeoTask = DataForSeoTask::query()->where('task_id', 'task-reviews-1')->first();
    expect($dataForSeoTask->status)->toBe(DataForSeoTaskStatus::Failed);
});

test('propaga la excepcion de presupuesto sin crear nada', function () {
    $project = Project::factory()->create(['google_business_place_id' => 'ChIJ12345']);

    Http::fake();
    app(CircuitBreaker::class)->trip('prueba');

    expect(fn () => app(SyncBusinessReviews::class)->execute($project, 20))
        ->toThrow(DataForSeoBudgetExceededException::class);

    Http::assertNothingSent();
    expect(DataForSeoTask::query()->count())->toBe(0);
});
