<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\Ranking;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un usuario del portal puede ver un proyecto de su propio cliente', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $user = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);

    $this->actingAs($user)
        ->get(route('portal.projects.show', $project))
        ->assertSuccessful()
        ->assertSee($project->name);
});

test('un usuario del portal no puede ver el proyecto de otro cliente', function () {
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $otherClient->id]);
    $user = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);

    $this->actingAs($user)
        ->get(route('portal.projects.show', $project))
        ->assertForbidden();
});

test('muestra las keywords activas con posicion, cambio, url y volumen, sin costos', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $user = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);

    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'keyword' => 'zapatos deportivos', 'is_active' => true, 'search_volume' => 1200]);
    Keyword::factory()->create(['project_id' => $project->id, 'is_active' => false, 'keyword' => 'keyword inactiva']);

    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-01', 'position' => 20]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-20', 'position' => 5, 'previous_position' => 20, 'url' => 'https://miempresa.com/zapatos']);

    $response = $this->actingAs($user)->get(route('portal.projects.show', $project));

    $response->assertSuccessful()
        ->assertSee('zapatos deportivos')
        ->assertSee('1,200')
        ->assertSee('https://miempresa.com/zapatos')
        ->assertDontSee('keyword inactiva')
        ->assertDontSee('costo', false)
        ->assertDontSee('presupuesto', false)
        ->assertDontSee('DataForSEO', false);
});

test('muestra los reportes completados con enlace de descarga', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $user = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);
    $template = ReportTemplate::factory()->create();

    $report = Report::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => ReportStatus::Completed,
        'file_path' => 'reports/x/report-x.pdf',
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ]);
    Report::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => ReportStatus::Pending,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
    ]);

    $response = $this->actingAs($user)->get(route('portal.projects.show', $project));

    $response->assertSuccessful()
        ->assertSee(route('reports.download', $report), false);
});
