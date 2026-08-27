<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\SiteAuditsRelationManager;
use App\Models\AuditIssue;
use App\Models\AuditLog;
use App\Models\DataForSeoTask;
use App\Models\Project;
use App\Models\SiteAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mountSiteAuditsRelationManager(Project $project, ?User $user = null): Testable
{
    $user ??= User::factory()->create(['role' => UserRole::Admin]);

    return Livewire::actingAs($user)->test(SiteAuditsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

test('lista las auditorias del proyecto y no las de otro', function () {
    $project = Project::factory()->create();
    $audit = SiteAudit::factory()->create(['project_id' => $project->id]);

    $otherProject = Project::factory()->create();
    $foreignAudit = SiteAudit::factory()->create(['project_id' => $otherProject->id]);

    $livewire = mountSiteAuditsRelationManager($project);

    $livewire->assertCanSeeTableRecords([$audit]);
    $livewire->assertCanNotSeeTableRecords([$foreignAudit]);
});

test('auditar sitio postea task_post, crea el audit y registra auditoria', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('trigger-site-audit:'.$admin->id);

    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/on_page/task_post' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.0, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-onpage-livewire', 'status_code' => 20000, 'status_message' => 'Ok.',
                'time' => '0.1 sec.', 'cost' => 0.0, 'result_count' => 0, 'path' => [], 'data' => [], 'result' => null,
            ]],
        ], 200),
    ]);

    mountSiteAuditsRelationManager($project, $admin)
        ->callTableAction('triggerAudit', data: ['max_crawl_pages' => 25])
        ->assertHasNoTableActionErrors();

    expect(SiteAudit::query()->where('project_id', $project->id)->where('task_id', 'task-onpage-livewire')->exists())->toBeTrue()
        ->and(DataForSeoTask::query()->where('task_id', 'task-onpage-livewire')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::PaidActionTriggered)->where('context->action', 'trigger_site_audit')->exists())->toBeTrue();
});

test('bloquea auditar sitio tras superar el maximo de intentos seguidos', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    RateLimiter::clear('trigger-site-audit:'.$admin->id);
    config(['cost_control.paid_action_rate_limit.max_attempts' => 1, 'cost_control.paid_action_rate_limit.decay_seconds' => 60]);

    $project = Project::factory()->create(['domain' => 'ejemplo.com']);

    Http::fake([
        '*/on_page/task_post' => Http::response([
            'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.0, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [['id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.', 'cost' => 0.0, 'result_count' => 0, 'path' => [], 'data' => [], 'result' => null]],
        ], 200),
    ]);

    $livewire = mountSiteAuditsRelationManager($project, $admin);
    $livewire->callTableAction('triggerAudit', data: ['max_crawl_pages' => 25]);
    Http::assertSentCount(1);

    $livewire->callTableAction('triggerAudit', data: ['max_crawl_pages' => 25]);
    Http::assertSentCount(1);
});

test('ver issues monta la accion sin errores para una auditoria con issues', function () {
    $project = Project::factory()->create();
    $audit = SiteAudit::factory()->create(['project_id' => $project->id]);
    AuditIssue::factory()->create(['audit_id' => $audit->id, 'url' => 'https://ejemplo.com/rota']);

    // El contenido del modal se renderiza como un "partial" de Livewire
    // (wire:partial="action-modals"), que no aparece en $livewire->html()
    // fuera de un round-trip real de navegador — se verifica que la
    // accion monta sin lanzar error aquí, y el contenido real (la vista
    // filament.modals.site-audit-issues) se verifica aparte con
    // assertViewRendersIssues más abajo y con Playwright.
    mountSiteAuditsRelationManager($project)
        ->mountTableAction('viewIssues', $audit->getKey())
        ->assertActionMounted();
});

test('la vista del modal de issues renderiza severidad, tipo, url y mensaje', function () {
    $audit = SiteAudit::factory()->create();
    $issue = AuditIssue::factory()->create(['audit_id' => $audit->id, 'url' => 'https://ejemplo.com/pagina-rota']);

    $html = view('filament.modals.site-audit-issues', ['issues' => collect([$issue])])->render();

    expect($html)->toContain('https://ejemplo.com/pagina-rota')
        ->and($html)->toContain($issue->issue_type->getLabel())
        ->and($html)->toContain($issue->message);
});

test('la vista del modal de issues muestra el mensaje vacio cuando no hay issues', function () {
    $html = view('filament.modals.site-audit-issues', ['issues' => collect()])->render();

    expect($html)->toContain(__('site_audits.issues.empty'));
});
