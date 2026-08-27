<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\BusinessReviewsRelationManager;
use App\Models\AuditLog;
use App\Models\BusinessReview;
use App\Models\DataForSeoTask;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mountBusinessReviewsRelationManager(Project $project, ?User $user = null): Testable
{
    $user ??= User::factory()->create(['role' => UserRole::Admin]);

    return Livewire::actingAs($user)->test(BusinessReviewsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

test('lista las reseñas del proyecto y no las de otro', function () {
    $project = Project::factory()->create();
    $review = BusinessReview::factory()->create(['project_id' => $project->id]);

    $otherProject = Project::factory()->create();
    $foreignReview = BusinessReview::factory()->create(['project_id' => $otherProject->id]);

    $livewire = mountBusinessReviewsRelationManager($project);

    $livewire->assertCanSeeTableRecords([$review]);
    $livewire->assertCanNotSeeTableRecords([$foreignReview]);
});

test('sincronizar reseñas postea task_post, crea la tarea y registra auditoria', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('sync-reviews:'.$admin->id);

    $project = Project::factory()->create(['google_business_place_id' => 'ChIJ12345']);

    Http::fake([
        '*/business_data/google/reviews/task_post' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.01, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-reviews-livewire', 'status_code' => 20000, 'status_message' => 'Ok.',
                'time' => '0.1 sec.', 'cost' => 0.01, 'result_count' => 0, 'path' => [], 'data' => [], 'result' => null,
            ]],
        ], 200),
    ]);

    mountBusinessReviewsRelationManager($project, $admin)
        ->callTableAction('syncReviews', data: ['depth' => 20])
        ->assertHasNoTableActionErrors();

    expect(DataForSeoTask::query()->where('task_id', 'task-reviews-livewire')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::PaidActionTriggered)->where('context->action', 'sync_business_reviews')->exists())->toBeTrue();
});

test('sincronizar reseñas sin place_id configurado no llama a la api', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('sync-reviews:'.$admin->id);

    $project = Project::factory()->create(['google_business_place_id' => null]);

    Http::fake();

    mountBusinessReviewsRelationManager($project, $admin)
        ->callTableAction('syncReviews', data: ['depth' => 20])
        ->assertHasNoTableActionErrors();

    Http::assertNothingSent();
    expect(DataForSeoTask::query()->count())->toBe(0);
});

test('bloquea sincronizar reseñas tras superar el maximo de intentos seguidos', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('sync-reviews:'.$admin->id);
    config(['cost_control.paid_action_rate_limit.max_attempts' => 1, 'cost_control.paid_action_rate_limit.decay_seconds' => 60]);

    $project = Project::factory()->create(['google_business_place_id' => 'ChIJ12345']);

    Http::fake([
        '*/business_data/google/reviews/task_post' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.01, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [['id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.', 'cost' => 0.01, 'result_count' => 0, 'path' => [], 'data' => [], 'result' => null]],
        ], 200),
    ]);

    $livewire = mountBusinessReviewsRelationManager($project, $admin);
    $livewire->callTableAction('syncReviews', data: ['depth' => 20]);
    Http::assertSentCount(1);

    $livewire->callTableAction('syncReviews', data: ['depth' => 20]);
    Http::assertSentCount(1);
});
