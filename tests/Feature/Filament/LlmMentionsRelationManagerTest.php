<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\LlmMentionsRelationManager;
use App\Models\AuditLog;
use App\Models\LlmMention;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mountLlmMentionsRelationManager(Project $project, ?User $user = null): Testable
{
    $user ??= User::factory()->create(['role' => UserRole::Admin]);

    return Livewire::actingAs($user)->test(LlmMentionsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

test('lista las menciones del proyecto y no las de otro', function () {
    $project = Project::factory()->create();
    $mention = LlmMention::factory()->create(['project_id' => $project->id]);

    $otherProject = Project::factory()->create();
    $foreignMention = LlmMention::factory()->create(['project_id' => $otherProject->id]);

    $livewire = mountLlmMentionsRelationManager($project);

    $livewire->assertCanSeeTableRecords([$mention]);
    $livewire->assertCanNotSeeTableRecords([$foreignMention]);
});

test('buscar menciones postea en vivo, guarda resultados y registra auditoria', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('search-llm-mentions:'.$admin->id);

    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/ai_optimization/llm_mentions/search/live' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.02, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
                'cost' => 0.02, 'result_count' => 1, 'path' => [], 'data' => [],
                'result' => [['items' => [
                    ['question' => '¿Qué es ejemplo.com?', 'answer' => 'Un sitio de ejemplo.', 'sources' => []],
                ]]],
            ]],
        ], 200),
    ]);

    mountLlmMentionsRelationManager($project, $admin)
        ->callTableAction('searchMentions', data: ['platform' => 'chat_gpt'])
        ->assertHasNoTableActionErrors();

    expect(LlmMention::query()->where('project_id', $project->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::PaidActionTriggered)->where('context->action', 'search_llm_mentions')->exists())->toBeTrue();
});

test('bloquea buscar menciones tras superar el maximo de intentos seguidos', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('search-llm-mentions:'.$admin->id);
    config(['cost_control.paid_action_rate_limit.max_attempts' => 1, 'cost_control.paid_action_rate_limit.decay_seconds' => 60]);

    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/ai_optimization/llm_mentions/search/live' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.02, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [['id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.', 'cost' => 0.02, 'result_count' => 1, 'path' => [], 'data' => [], 'result' => [['items' => []]]]],
        ], 200),
    ]);

    $livewire = mountLlmMentionsRelationManager($project, $admin);
    $livewire->callTableAction('searchMentions', data: ['platform' => 'chat_gpt']);
    Http::assertSentCount(1);

    $livewire->callTableAction('searchMentions', data: ['platform' => 'chat_gpt']);
    Http::assertSentCount(1);
});

test('ver mencion monta la accion sin errores', function () {
    $project = Project::factory()->create();
    $mention = LlmMention::factory()->create(['project_id' => $project->id]);

    mountLlmMentionsRelationManager($project)
        ->mountTableAction('viewMention', $mention->getKey())
        ->assertActionMounted();
});

test('la vista de detalle de mencion renderiza pregunta, respuesta y fuentes', function () {
    $mention = LlmMention::factory()->create([
        'question' => '¿Qué es ejemplo.com?',
        'answer' => 'Un sitio de ejemplo muy confiable.',
        'sources' => [['url' => 'https://ejemplo.com/acerca']],
    ]);

    $html = view('filament.modals.llm-mention-detail', ['mention' => $mention])->render();

    expect($html)->toContain('¿Qué es ejemplo.com?')
        ->and($html)->toContain('Un sitio de ejemplo muy confiable.')
        ->and($html)->toContain('https://ejemplo.com/acerca');
});
