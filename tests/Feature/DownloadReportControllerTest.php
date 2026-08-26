<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('un usuario interno puede descargar un reporte completado', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $report = Report::factory()->create(['status' => ReportStatus::Completed, 'file_path' => 'reports/1/report-1.pdf']);
    Storage::disk('local')->put($report->file_path, '%PDF-1.4 contenido de prueba');

    $this->actingAs($admin)
        ->get(route('reports.download', $report))
        ->assertSuccessful();
});

test('un usuario con rol client no puede descargar el reporte de otro cliente', function () {
    Storage::fake('local');

    $client = User::factory()->create(['role' => UserRole::Client]);
    $report = Report::factory()->create(['status' => ReportStatus::Completed, 'file_path' => 'reports/1/report-1.pdf']);
    Storage::disk('local')->put($report->file_path, '%PDF-1.4 contenido de prueba');

    $this->actingAs($client)
        ->get(route('reports.download', $report))
        ->assertForbidden();
});

test('un usuario del portal puede descargar un reporte de su propio proyecto', function () {
    Storage::fake('local');

    $ownerClient = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $ownerClient->id]);
    $portalUser = User::factory()->create(['role' => UserRole::Client, 'client_id' => $ownerClient->id]);
    $report = Report::factory()->create(['project_id' => $project->id, 'status' => ReportStatus::Completed, 'file_path' => 'reports/1/report-1.pdf']);
    Storage::disk('local')->put($report->file_path, '%PDF-1.4 contenido de prueba');

    $this->actingAs($portalUser)
        ->get(route('reports.download', $report))
        ->assertSuccessful();
});

test('un invitado se redirige al login del panel en vez de un error', function () {
    $report = Report::factory()->create(['status' => ReportStatus::Completed, 'file_path' => 'reports/1/report-1.pdf']);

    $this->get(route('reports.download', $report))
        ->assertRedirect(route('filament.admin.auth.login'));
});

test('devuelve 404 si el reporte no esta completado', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $report = Report::factory()->create(['status' => ReportStatus::Pending, 'file_path' => null]);

    $this->actingAs($admin)
        ->get(route('reports.download', $report))
        ->assertNotFound();
});
