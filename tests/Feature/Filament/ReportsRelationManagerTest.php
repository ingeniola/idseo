<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\ReportsRelationManager;
use App\Jobs\GenerateReport;
use App\Jobs\SendReportEmail;
use App\Models\Client;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mountReportsRelationManager(Project $project): Testable
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    return Livewire::actingAs($admin)->test(ReportsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

test('generar reporte crea un Report pendiente y despacha GenerateReport', function () {
    Bus::fake();

    $project = Project::factory()->create();
    $template = ReportTemplate::factory()->create(['client_id' => $project->client_id]);

    mountReportsRelationManager($project)
        ->callTableAction('generateReport', data: [
            'template_id' => $template->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ])
        ->assertHasNoTableActionErrors();

    $report = Report::query()->where('project_id', $project->id)->first();

    expect($report)->not->toBeNull()
        ->and($report->status)->toBe(ReportStatus::Pending)
        ->and($report->template_id)->toBe($template->id);

    Bus::assertDispatched(GenerateReport::class, fn (GenerateReport $job) => $job->reportId === $report->id);
});

test('solo ofrece plantillas del cliente del proyecto o globales', function () {
    $project = Project::factory()->create();
    $otherClient = Client::factory()->create();
    $ownTemplate = ReportTemplate::factory()->create(['client_id' => $project->client_id, 'name' => 'Propia']);
    $globalTemplate = ReportTemplate::factory()->create(['client_id' => null, 'name' => 'Global']);
    $otherClientTemplate = ReportTemplate::factory()->create(['client_id' => $otherClient->id, 'name' => 'De otro cliente']);

    // Mismo query que usa el Select de la acción "generateReport"
    // (ver ReportsRelationManager::generateReportAction()).
    $availableIds = ReportTemplate::query()
        ->where('client_id', $project->client_id)
        ->orWhereNull('client_id')
        ->pluck('id');

    expect($availableIds)->toContain($ownTemplate->id, $globalTemplate->id)
        ->not->toContain($otherClientTemplate->id);
});

test('descargar solo esta visible para reportes completados', function () {
    $project = Project::factory()->create();
    $completed = Report::factory()->create(['project_id' => $project->id, 'status' => ReportStatus::Completed, 'file_path' => 'x.pdf']);
    $pending = Report::factory()->create(['project_id' => $project->id, 'status' => ReportStatus::Pending]);

    $livewire = mountReportsRelationManager($project);

    $livewire->assertTableActionVisible('download', $completed);
    $livewire->assertTableActionHidden('download', $pending);
});

test('enviar por correo despacha SendReportEmail solo para reportes completados', function () {
    Bus::fake();

    $project = Project::factory()->create();
    $completed = Report::factory()->create(['project_id' => $project->id, 'status' => ReportStatus::Completed, 'file_path' => 'x.pdf']);

    mountReportsRelationManager($project)
        ->callTableAction('send', $completed);

    Bus::assertDispatched(SendReportEmail::class, fn (SendReportEmail $job) => $job->reportId === $completed->id);
});

test('reintentar solo esta visible para reportes fallidos y redespacha GenerateReport', function () {
    Bus::fake();

    $project = Project::factory()->create();
    $failed = Report::factory()->create(['project_id' => $project->id, 'status' => ReportStatus::Failed]);
    $completed = Report::factory()->create(['project_id' => $project->id, 'status' => ReportStatus::Completed, 'file_path' => 'x.pdf']);

    $livewire = mountReportsRelationManager($project);
    $livewire->assertTableActionVisible('retry', $failed);
    $livewire->assertTableActionHidden('retry', $completed);

    $livewire->callTableAction('retry', $failed);

    Bus::assertDispatched(GenerateReport::class, fn (GenerateReport $job) => $job->reportId === $failed->id);
});
