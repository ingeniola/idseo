<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\KeywordsRelationManager;
use App\Models\AuditLog;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'cost_control.paid_action_rate_limit.max_attempts' => 2,
        'cost_control.paid_action_rate_limit.decay_seconds' => 60,
    ]);
});

test('bloquea la accion tras superar el maximo de intentos seguidos', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('enrich-volume:'.$admin->id);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    Http::fake([
        '*/keywords_data/google_ads/search_volume/live' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.05, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
                'cost' => 0.05, 'result_count' => 0, 'path' => [], 'data' => [], 'result' => [],
            ]],
        ], 200),
    ]);

    $livewire = Livewire::actingAs($admin)->test(KeywordsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);

    // Intentos 1 y 2 (el máximo configurado): pasan y le pegan a la API.
    $livewire->callTableBulkAction('enrichVolume', [$keyword]);
    $livewire->callTableBulkAction('enrichVolume', [$keyword]);
    Http::assertSentCount(2);

    // Intento 3: bloqueado por el rate limit, no llega a la API.
    $livewire->callTableBulkAction('enrichVolume', [$keyword]);
    Http::assertSentCount(2);
});

test('cada usuario tiene su propio limite', function () {
    $admin1 = User::factory()->create(['role' => UserRole::Admin]);
    $admin2 = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('enrich-volume:'.$admin1->id);
    RateLimiter::clear('enrich-volume:'.$admin2->id);

    RateLimiter::hit('enrich-volume:'.$admin1->id, 60);
    RateLimiter::hit('enrich-volume:'.$admin1->id, 60);

    expect(RateLimiter::tooManyAttempts('enrich-volume:'.$admin1->id, 2))->toBeTrue()
        ->and(RateLimiter::tooManyAttempts('enrich-volume:'.$admin2->id, 2))->toBeFalse();
});

test('dispara un evento de auditoria de accion paga al ejecutarse', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('enrich-volume:'.$admin->id);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    Http::fake([
        '*/keywords_data/google_ads/search_volume/live' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.05, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
                'cost' => 0.05, 'result_count' => 0, 'path' => [], 'data' => [], 'result' => [],
            ]],
        ], 200),
    ]);

    Livewire::actingAs($admin)->test(KeywordsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ])->callTableBulkAction('enrichVolume', [$keyword]);

    expect(AuditLog::query()->where('event', AuditEvent::PaidActionTriggered)->where('user_id', $admin->id)->exists())->toBeTrue();
});
