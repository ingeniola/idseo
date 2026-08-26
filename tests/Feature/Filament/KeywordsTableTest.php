<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\KeywordsRelationManager;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\Ranking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mountKeywordsRelationManager(Project $project): Testable
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    return Livewire::actingAs($admin)->test(KeywordsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

test('la columna de posicion actual muestra la ultima posicion conocida', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-01', 'position' => 12, 'previous_position' => null]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-08', 'position' => 5, 'previous_position' => 12]);

    mountKeywordsRelationManager($project)
        ->assertTableColumnStateSet('latestRanking.position', 5, $keyword)
        ->assertTableColumnStateSet('position_change', 7, $keyword)
        ->assertTableColumnFormattedStateSet('position_change', '▲ 7', $keyword);
});

test('la columna de cambio muestra bajo cuando la posicion empeora', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-08', 'position' => 20, 'previous_position' => 5]);

    mountKeywordsRelationManager($project)
        ->assertTableColumnStateSet('position_change', -15, $keyword)
        ->assertTableColumnFormattedStateSet('position_change', '▼ 15', $keyword);
});

test('la columna de cambio muestra guion sin ranking previo', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-08', 'position' => 20, 'previous_position' => null]);

    mountKeywordsRelationManager($project)
        ->assertTableColumnStateSet('position_change', null, $keyword)
        ->assertTableColumnFormattedStateSet('position_change', '—', $keyword);
});

test('la columna de URL rankeando enlaza a la url de la ultima ranking', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-08', 'url' => 'https://miempresa.com/pagina']);

    mountKeywordsRelationManager($project)
        ->assertTableColumnStateSet('latestRanking.url', 'https://miempresa.com/pagina', $keyword);
});

test('filtra keywords por etiqueta', function () {
    $project = Project::factory()->create();
    $conMarca = Keyword::factory()->create(['project_id' => $project->id, 'tags' => ['marca']]);
    $sinMarca = Keyword::factory()->create(['project_id' => $project->id, 'tags' => ['generica']]);

    mountKeywordsRelationManager($project)
        ->filterTable('tags', ['marca'])
        ->assertCanSeeTableRecords([$conMarca])
        ->assertCanNotSeeTableRecords([$sinMarca]);
});

test('filtra keywords por rango de posicion', function () {
    $project = Project::factory()->create();
    $top = Keyword::factory()->create(['project_id' => $project->id]);
    $lejos = Keyword::factory()->create(['project_id' => $project->id]);
    Ranking::factory()->create(['keyword_id' => $top->id, 'checked_at' => '2026-08-08', 'position' => 3]);
    Ranking::factory()->create(['keyword_id' => $lejos->id, 'checked_at' => '2026-08-08', 'position' => 50]);

    mountKeywordsRelationManager($project)
        ->filterTable('position_range', ['min' => 1, 'max' => 10])
        ->assertCanSeeTableRecords([$top])
        ->assertCanNotSeeTableRecords([$lejos]);
});

test('filtra keywords por movimiento subio', function () {
    $project = Project::factory()->create();
    $subio = Keyword::factory()->create(['project_id' => $project->id]);
    $bajo = Keyword::factory()->create(['project_id' => $project->id]);
    Ranking::factory()->create(['keyword_id' => $subio->id, 'checked_at' => '2026-08-08', 'position' => 3, 'previous_position' => 10]);
    Ranking::factory()->create(['keyword_id' => $bajo->id, 'checked_at' => '2026-08-08', 'position' => 10, 'previous_position' => 3]);

    mountKeywordsRelationManager($project)
        ->filterTable('movement', 'up')
        ->assertCanSeeTableRecords([$subio])
        ->assertCanNotSeeTableRecords([$bajo]);
});

test('filtra keywords por movimiento sin dato', function () {
    $project = Project::factory()->create();
    $sinDato = Keyword::factory()->create(['project_id' => $project->id]);
    $conDato = Keyword::factory()->create(['project_id' => $project->id]);
    Ranking::factory()->create(['keyword_id' => $conDato->id, 'checked_at' => '2026-08-08', 'position' => 10, 'previous_position' => 3]);

    mountKeywordsRelationManager($project)
        ->filterTable('movement', 'none')
        ->assertCanSeeTableRecords([$sinDato])
        ->assertCanNotSeeTableRecords([$conDato]);
});

test('la accion ver evolucion abre sin errores', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-08', 'position' => 5]);

    mountKeywordsRelationManager($project)
        ->mountTableAction('rankingHistory', $keyword)
        ->assertOk()
        ->assertSee($keyword->keyword);
});
