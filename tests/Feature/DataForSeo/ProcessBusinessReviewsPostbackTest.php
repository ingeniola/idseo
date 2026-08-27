<?php

declare(strict_types=1);

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Jobs\ProcessBusinessReviewsPostback;
use App\Models\BusinessReview;
use App\Models\DataForSeoTask;
use App\Models\Project;
use App\Models\SiteAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function reviewsPostbackPayload(string $taskId = 'task-reviews-1', int $statusCode = 20000): array
{
    return [
        'tasks' => [[
            'id' => $taskId, 'status_code' => $statusCode,
            'status_message' => $statusCode === 20000 ? 'Ok.' : 'Error.',
        ]],
    ];
}

function pendingReviewsTask(Project $project, string $taskId = 'task-reviews-1'): DataForSeoTask
{
    return DataForSeoTask::factory()->create([
        'task_id' => $taskId,
        'endpoint' => 'business_data/google/reviews/task_post',
        'taskable_type' => Project::class,
        'taskable_id' => $project->id,
        'status' => DataForSeoTaskStatus::Pending,
        'payload_received' => json_encode(reviewsPostbackPayload($taskId)),
    ]);
}

function reviewsTaskGetResponse(array $items, float $cost = 0.05): array
{
    return [
        'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
        'cost' => $cost, 'tasks_count' => 1, 'tasks_error' => 0,
        'tasks' => [[
            'id' => 'task-reviews-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => $cost, 'result_count' => 1, 'path' => [], 'data' => [],
            'result' => [['items' => $items]],
        ]],
    ];
}

test('guarda las reseñas a partir del task_get', function () {
    $project = Project::factory()->create(['google_business_place_id' => 'ChIJ12345']);
    $task = pendingReviewsTask($project);

    Http::fake([
        '*/business_data/google/reviews/task_get/*' => Http::response(reviewsTaskGetResponse([
            [
                'review_id' => 'rev-1',
                'profile_name' => 'Juan Pérez',
                'profile_image_url' => 'https://example.com/juan.jpg',
                'rating' => ['rating_type' => 'Max5', 'value' => 5, 'votes_count' => null, 'rating_max' => 5],
                'review_text' => 'Excelente servicio.',
                'timestamp' => '2026-08-01 10:00:00 +00:00',
                'owner_answer' => 'Gracias por tu visita.',
                'owner_timestamp' => '2026-08-02 09:00:00 +00:00',
                'local_guide' => true,
            ],
            [
                'review_id' => 'rev-2',
                'profile_name' => 'María López',
                'rating' => ['value' => 2],
                'review_text' => 'Podría mejorar.',
                'timestamp' => '2026-08-03 12:00:00 +00:00',
                'owner_answer' => null,
                'local_guide' => false,
            ],
        ]), 200),
    ]);

    (new ProcessBusinessReviewsPostback($task->id))->handle(app(DataForSeoClient::class));

    expect($task->fresh()->status)->toBe(DataForSeoTaskStatus::Completed);

    $review1 = BusinessReview::query()->where('project_id', $project->id)->where('review_id', 'rev-1')->first();
    expect($review1)->not->toBeNull()
        ->and($review1->reviewer_name)->toBe('Juan Pérez')
        ->and($review1->rating)->toBe(5)
        ->and($review1->review_text)->toBe('Excelente servicio.')
        ->and($review1->owner_answer)->toBe('Gracias por tu visita.')
        ->and($review1->owner_answered_at)->not->toBeNull()
        ->and($review1->is_local_guide)->toBeTrue();

    $review2 = BusinessReview::query()->where('project_id', $project->id)->where('review_id', 'rev-2')->first();
    expect($review2)->not->toBeNull()
        ->and($review2->rating)->toBe(2)
        ->and($review2->owner_answer)->toBeNull()
        ->and($review2->owner_answered_at)->toBeNull()
        ->and($review2->is_local_guide)->toBeFalse();
});

test('un segundo sync de la misma reseña hace upsert en vez de duplicar', function () {
    $project = Project::factory()->create(['google_business_place_id' => 'ChIJ12345']);
    $task = pendingReviewsTask($project);

    Http::fake([
        '*/business_data/google/reviews/task_get/*' => Http::response(reviewsTaskGetResponse([
            ['review_id' => 'rev-1', 'profile_name' => 'Juan', 'rating' => ['value' => 4], 'review_text' => 'Bien.', 'local_guide' => false],
        ]), 200),
    ]);

    (new ProcessBusinessReviewsPostback($task->id))->handle(app(DataForSeoClient::class));

    $task->update(['status' => DataForSeoTaskStatus::Pending]);
    (new ProcessBusinessReviewsPostback($task->id))->handle(app(DataForSeoClient::class));

    expect(BusinessReview::query()->where('project_id', $project->id)->count())->toBe(1);
});

test('marca failed si el status_code del postback no es exitoso, sin llamar a task_get', function () {
    $project = Project::factory()->create();
    $task = DataForSeoTask::factory()->create([
        'task_id' => 'task-reviews-1',
        'endpoint' => 'business_data/google/reviews/task_post',
        'taskable_type' => Project::class,
        'taskable_id' => $project->id,
        'status' => DataForSeoTaskStatus::Pending,
        'payload_received' => json_encode(reviewsPostbackPayload(statusCode: 40501)),
    ]);

    Http::fake();

    (new ProcessBusinessReviewsPostback($task->id))->handle(app(DataForSeoClient::class));

    expect($task->fresh()->status)->toBe(DataForSeoTaskStatus::Failed);
    Http::assertNothingSent();
});

test('no hace nada si la tarea ya no esta pending', function () {
    $project = Project::factory()->create();
    $task = pendingReviewsTask($project);
    $task->update(['status' => DataForSeoTaskStatus::Completed]);

    Http::fake();

    (new ProcessBusinessReviewsPostback($task->id))->handle(app(DataForSeoClient::class));

    Http::assertNothingSent();
});

test('registra error si la tarea no corresponde a un Project', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    $task = DataForSeoTask::factory()->create([
        'task_id' => 'task-otro-tipo',
        'taskable_type' => SiteAudit::class,
        'taskable_id' => SiteAudit::factory()->create(['project_id' => $otherProject->id])->id,
        'status' => DataForSeoTaskStatus::Pending,
        'payload_received' => json_encode(reviewsPostbackPayload('task-otro-tipo')),
    ]);

    Http::fake();

    (new ProcessBusinessReviewsPostback($task->id))->handle(app(DataForSeoClient::class));

    expect($task->fresh()->status)->toBe(DataForSeoTaskStatus::Pending);
    Http::assertNothingSent();
});
