<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\KeywordIdeasRelationManager;
use App\Models\AuditLog;
use App\Models\Keyword;
use App\Models\KeywordIdea;
use App\Models\KeywordResearchSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mountKeywordIdeasRelationManager(Project $project, ?User $user = null): Testable
{
    $user ??= User::factory()->create(['role' => UserRole::Admin]);

    return Livewire::actingAs($user)->test(KeywordIdeasRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

function fakeKeywordIdeasResponse(): void
{
    Http::fake([
        '*/dataforseo_labs/google/keyword_ideas/live' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.02, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
                'cost' => 0.02, 'result_count' => 1, 'path' => [], 'data' => [],
                'result' => [['seed_keywords' => ['zapatos'], 'total_count' => 1, 'items_count' => 1, 'items' => [
                    ['keyword' => 'zapatos baratos', 'keyword_info' => ['search_volume' => 500, 'cpc' => 0.2, 'competition' => 0.3], 'keyword_properties' => ['keyword_difficulty' => 20], 'search_intent_info' => ['main_intent' => 'commercial']],
                ]]],
            ]],
        ], 200),
    ]);
}

test('buscar ideas crea la sesion y sus ideas, y registra auditoria', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('keyword-research:'.$admin->id);

    $project = Project::factory()->create();
    fakeKeywordIdeasResponse();

    mountKeywordIdeasRelationManager($project, $admin)
        ->callTableAction('searchIdeas', data: ['seed_keyword' => 'zapatos'])
        ->assertHasNoTableActionErrors();

    expect(KeywordResearchSession::query()->where('project_id', $project->id)->count())->toBe(1)
        ->and(KeywordIdea::query()->where('keyword', 'zapatos baratos')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::PaidActionTriggered)->exists())->toBeTrue();
});

test('bloquea la busqueda tras superar el maximo de intentos seguidos', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('keyword-research:'.$admin->id);

    config([
        'cost_control.paid_action_rate_limit.max_attempts' => 1,
        'cost_control.paid_action_rate_limit.decay_seconds' => 60,
    ]);

    $project = Project::factory()->create();
    fakeKeywordIdeasResponse();

    $livewire = mountKeywordIdeasRelationManager($project, $admin);

    $livewire->callTableAction('searchIdeas', data: ['seed_keyword' => 'zapatos']);
    Http::assertSentCount(1);

    $livewire->callTableAction('searchIdeas', data: ['seed_keyword' => 'otra']);
    Http::assertSentCount(1);
});

test('promover crea keywords rastreadas con los datos de la idea y marca is_selected', function () {
    $project = Project::factory()->create(['default_location_code' => 2340, 'default_language_code' => 'es']);
    $session = KeywordResearchSession::factory()->create(['project_id' => $project->id]);
    $idea = KeywordIdea::factory()->create([
        'session_id' => $session->id,
        'keyword' => 'zapatos deportivos',
        'search_volume' => 720,
        'cpc' => 0.4,
        'competition' => 0.5,
    ]);

    mountKeywordIdeasRelationManager($project)
        ->callTableBulkAction('promote', [$idea]);

    $keyword = Keyword::query()->where('project_id', $project->id)->where('keyword', 'zapatos deportivos')->first();

    expect($keyword)->not->toBeNull()
        ->and($keyword->location_code)->toBe(2340)
        ->and($keyword->language_code)->toBe('es')
        ->and($keyword->search_volume)->toBe(720)
        ->and($idea->fresh()->is_selected)->toBeTrue();
});

test('promover una idea ya existente como keyword no duplica', function () {
    $project = Project::factory()->create(['default_location_code' => 2340, 'default_language_code' => 'es']);
    Keyword::factory()->create([
        'project_id' => $project->id,
        'keyword' => 'zapatos deportivos',
        'location_code' => 2340,
        'language_code' => 'es',
    ]);

    $session = KeywordResearchSession::factory()->create(['project_id' => $project->id]);
    $idea = KeywordIdea::factory()->create(['session_id' => $session->id, 'keyword' => 'zapatos deportivos']);

    mountKeywordIdeasRelationManager($project)
        ->callTableBulkAction('promote', [$idea]);

    expect(Keyword::query()->where('project_id', $project->id)->where('keyword', 'zapatos deportivos')->count())->toBe(1)
        ->and($idea->fresh()->is_selected)->toBeTrue();
});
