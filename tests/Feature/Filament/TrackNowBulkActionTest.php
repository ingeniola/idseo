<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\KeywordsRelationManager;
use App\Jobs\TrackKeywordsNow;
use App\Models\AuditLog;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'cost_control.paid_action_rate_limit.max_attempts' => 2,
        'cost_control.paid_action_rate_limit.decay_seconds' => 60,
    ]);
});

test('dispara TrackKeywordsNow con las keywords seleccionadas, sin importar si estan due', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('track-now:'.$admin->id);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    Livewire::actingAs($admin)->test(KeywordsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ])->callTableBulkAction('trackNow', [$keyword]);

    Queue::assertPushed(TrackKeywordsNow::class, fn (TrackKeywordsNow $job) => $job->keywordIds === [$keyword->id]);
});

test('dispara un evento de auditoria de accion paga al ejecutarse', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('track-now:'.$admin->id);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    Livewire::actingAs($admin)->test(KeywordsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ])->callTableBulkAction('trackNow', [$keyword]);

    expect(AuditLog::query()->where('event', AuditEvent::PaidActionTriggered)->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('bloquea la accion tras superar el maximo de intentos seguidos', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('track-now:'.$admin->id);

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    $livewire = Livewire::actingAs($admin)->test(KeywordsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);

    $livewire->callTableBulkAction('trackNow', [$keyword]);
    $livewire->callTableBulkAction('trackNow', [$keyword]);
    Queue::assertPushed(TrackKeywordsNow::class, 2);

    $livewire->callTableBulkAction('trackNow', [$keyword]);
    Queue::assertPushed(TrackKeywordsNow::class, 2);
});
