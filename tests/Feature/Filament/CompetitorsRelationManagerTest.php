<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\CompetitorsRelationManager;
use App\Models\Project;
use App\Models\SerpCompetitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mountCompetitorsRelationManager(Project $project)
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    return Livewire::actingAs($admin)->test(CompetitorsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

test('lista solo los competidores calculados en la fecha mas reciente', function () {
    $project = Project::factory()->create();

    $old = SerpCompetitor::factory()->create([
        'project_id' => $project->id,
        'domain' => 'viejo.com',
        'calculated_at' => Carbon::yesterday()->toDateString(),
    ]);
    $recent = SerpCompetitor::factory()->create([
        'project_id' => $project->id,
        'domain' => 'reciente.com',
        'calculated_at' => Carbon::today()->toDateString(),
    ]);

    mountCompetitorsRelationManager($project)
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

test('no ofrece crear, editar ni borrar: los datos los calcula el job', function () {
    $project = Project::factory()->create();
    SerpCompetitor::factory()->create(['project_id' => $project->id, 'calculated_at' => Carbon::today()->toDateString()]);

    $livewire = mountCompetitorsRelationManager($project);

    expect($livewire->instance()->getTable()->getHeaderActions())->toBeEmpty()
        ->and($livewire->instance()->getTable()->getRecordActions())->toBeEmpty();
});
