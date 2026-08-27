<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\SearchConsoleRelationManager;
use App\Models\Project;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mountSearchConsoleRelationManager(Project $project)
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    return Livewire::actingAs($admin)->test(SearchConsoleRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

test('sin conexion muestra la accion de conectar y no la de desconectar', function () {
    $project = Project::factory()->create();

    $livewire = mountSearchConsoleRelationManager($project);

    $livewire->assertTableActionVisible('connect');
    $livewire->assertTableActionHidden('disconnect');
});

test('con conexion muestra la accion de desconectar y no la de conectar', function () {
    $project = Project::factory()->create();
    SearchConsoleConnection::factory()->create(['project_id' => $project->id, 'site_url' => 'sc-domain:ejemplo.com']);

    $livewire = mountSearchConsoleRelationManager($project);

    $livewire->assertTableActionHidden('connect');
    $livewire->assertTableActionVisible('disconnect');
});

test('desconectar borra la conexion y la propia tabla refleja el cambio sin recargar', function () {
    $project = Project::factory()->create();
    SearchConsoleConnection::factory()->create(['project_id' => $project->id]);

    $livewire = mountSearchConsoleRelationManager($project);
    $livewire->callTableAction('disconnect');

    expect($project->fresh()->searchConsoleConnection)->toBeNull();

    // Regresión: el Project en memoria del Livewire tenía la relación
    // ya cargada antes del delete(); sin unsetRelation() en la Action,
    // estos botones seguían mostrando el estado "conectado" en el
    // mismo render, aunque la base de datos ya estuviera al día.
    $livewire->assertTableActionVisible('connect');
    $livewire->assertTableActionHidden('disconnect');
});

test('lista las metricas del proyecto', function () {
    $project = Project::factory()->create();
    SearchConsoleMetric::factory()->create(['project_id' => $project->id, 'query' => 'zapatos deportivos', 'clicks' => 42]);

    $otherProject = Project::factory()->create();
    $foreignMetric = SearchConsoleMetric::factory()->create(['project_id' => $otherProject->id]);

    $livewire = mountSearchConsoleRelationManager($project);

    $livewire->assertCanSeeTableRecords(SearchConsoleMetric::where('project_id', $project->id)->get());
    $livewire->assertCanNotSeeTableRecords([$foreignMetric]);
});
