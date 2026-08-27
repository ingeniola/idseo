<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\BacklinksRelationManager;
use App\Models\AuditLog;
use App\Models\Backlink;
use App\Models\BacklinkSummary;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mountBacklinksRelationManager(Project $project, ?User $user = null): Testable
{
    $user ??= User::factory()->create(['role' => UserRole::Admin]);

    return Livewire::actingAs($user)->test(BacklinksRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

function fakeBacklinkEndpoints(): void
{
    $envelope = fn (array $result) => [
        'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
        'cost' => 0.01, 'tasks_count' => 1, 'tasks_error' => 0,
        'tasks' => [[
            'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.01, 'result_count' => 1, 'path' => [], 'data' => [],
            'result' => [$result],
        ]],
    ];

    Http::fake([
        '*/backlinks/summary/live' => Http::response($envelope(['backlinks' => 100, 'referring_domains' => 10, 'rank' => 400]), 200),
        '*/backlinks/referring_domains/live' => Http::response($envelope(['items' => []]), 200),
        '*/backlinks/backlinks/live' => Http::response($envelope([
            'items' => [[
                'url_from' => 'https://otrositio.com/x', 'domain_from' => 'otrositio.com',
                'url_to' => 'https://ejemplo.com/y', 'dofollow' => true, 'is_lost' => false,
            ]],
        ]), 200),
    ]);
}

test('actualizar perfil sincroniza y registra auditoria', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('sync-backlinks:'.$admin->id);

    $project = Project::factory()->create(['domain' => 'ejemplo.com']);
    fakeBacklinkEndpoints();

    mountBacklinksRelationManager($project, $admin)
        ->callTableAction('syncBacklinks')
        ->assertHasNoTableActionErrors();

    expect(BacklinkSummary::query()->where('project_id', $project->id)->exists())->toBeTrue()
        ->and(Backlink::query()->where('project_id', $project->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::PaidActionTriggered)->where('context->action', 'sync_backlinks')->exists())->toBeTrue();
});

test('bloquea actualizar perfil tras superar el maximo de intentos seguidos', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('sync-backlinks:'.$admin->id);
    config(['cost_control.paid_action_rate_limit.max_attempts' => 1, 'cost_control.paid_action_rate_limit.decay_seconds' => 60]);

    $project = Project::factory()->create(['domain' => 'ejemplo.com']);
    fakeBacklinkEndpoints();

    $livewire = mountBacklinksRelationManager($project, $admin);
    $livewire->callTableAction('syncBacklinks');
    Http::assertSentCount(3);

    $livewire->callTableAction('syncBacklinks');
    Http::assertSentCount(3);
});

test('comparar con competidor guarda el resumen y registra auditoria', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('compare-backlinks:'.$admin->id);

    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/backlinks/summary/live' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.01, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
                'cost' => 0.01, 'result_count' => 1, 'path' => [], 'data' => [],
                'result' => [['backlinks' => 5000, 'referring_domains' => 300, 'rank' => 700]],
            ]],
        ], 200),
    ]);

    mountBacklinksRelationManager($project, $admin)
        ->callTableAction('compareBacklinks', data: ['domain' => 'competidor.com'])
        ->assertHasNoTableActionErrors();

    expect(BacklinkSummary::query()->where('project_id', $project->id)->where('domain', 'competidor.com')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::PaidActionTriggered)->where('context->action', 'compare_backlinks')->exists())->toBeTrue();
});

test('lista los backlinks del proyecto', function () {
    $project = Project::factory()->create();
    $backlink = Backlink::factory()->create(['project_id' => $project->id]);

    $otherProject = Project::factory()->create();
    $foreignBacklink = Backlink::factory()->create(['project_id' => $otherProject->id]);

    $livewire = mountBacklinksRelationManager($project);

    $livewire->assertCanSeeTableRecords([$backlink]);
    $livewire->assertCanNotSeeTableRecords([$foreignBacklink]);
});
