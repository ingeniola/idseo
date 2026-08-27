<?php

declare(strict_types=1);

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\KeywordsRelationManager;
use App\Jobs\TrackKeywordsNow;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\Location;
use App\Models\Project;
use App\Models\Ranking;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

test('muestra procesando bajo la posicion mientras hay una tarea de rank tracking pendiente', function () {
    $project = Project::factory()->create();
    $procesando = Keyword::factory()->create(['project_id' => $project->id]);
    $sinTarea = Keyword::factory()->create(['project_id' => $project->id]);

    DataForSeoTask::factory()->create([
        'taskable_type' => Keyword::class,
        'taskable_id' => $procesando->id,
        'status' => DataForSeoTaskStatus::Pending,
    ]);

    mountKeywordsRelationManager($project)
        ->assertTableColumnFormattedStateSet('latestRanking.position', 'Procesando…', $procesando)
        ->assertTableColumnFormattedStateSet('latestRanking.position', null, $sinTarea)
        ->assertSee('Procesando…');
});

test('no muestra procesando si la tarea de rank tracking ya se completo', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    DataForSeoTask::factory()->create([
        'taskable_type' => Keyword::class,
        'taskable_id' => $keyword->id,
        'status' => DataForSeoTaskStatus::Completed,
    ]);

    mountKeywordsRelationManager($project)
        ->assertTableColumnFormattedStateSet('latestRanking.position', null, $keyword);
});

test('el selector de idioma para agregar keywords solo ofrece idiomas validos para keywords_data/google_ads', function () {
    $project = Project::factory()->create();
    Language::query()->create(['language_code' => 'es', 'language_name' => 'Spanish', 'valid_for_google_ads_keywords' => true]);
    Language::query()->create(['language_code' => 'es-419', 'language_name' => 'Spanish (Latin America)', 'valid_for_google_ads_keywords' => false]);

    mountKeywordsRelationManager($project)
        ->mountTableAction('create')
        ->assertFormFieldExists(
            'language_code',
            fn (Select $field) => array_key_exists('es', $field->getOptions())
                && ! array_key_exists('es-419', $field->getOptions()),
        );
});

test('crear una keyword individual dispara TrackKeywordsNow para esa keyword', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $location = Location::factory()->create();
    $language = Language::query()->create(['language_code' => 'es', 'language_name' => 'Spanish', 'valid_for_google_ads_keywords' => true]);

    mountKeywordsRelationManager($project)
        ->callTableAction('create', data: [
            'keyword' => 'clinica dental',
            'location_code' => $location->location_code,
            'language_code' => $language->language_code,
            'is_active' => true,
        ])
        ->assertHasNoTableActionErrors();

    $keyword = Keyword::query()->where('keyword', 'clinica dental')->firstOrFail();

    Queue::assertPushed(TrackKeywordsNow::class, fn (TrackKeywordsNow $job) => $job->keywordIds === [$keyword->id]);
});

test('el pegado masivo dispara TrackKeywordsNow solo con las keywords nuevas, no las que ya existian', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $location = Location::factory()->create();
    $language = Language::query()->create(['language_code' => 'es', 'language_name' => 'Spanish', 'valid_for_google_ads_keywords' => true]);

    $existing = Keyword::factory()->create([
        'project_id' => $project->id,
        'keyword' => 'ya existia',
        'location_code' => $location->location_code,
        'language_code' => $language->language_code,
    ]);

    mountKeywordsRelationManager($project)
        ->callTableAction('bulkPaste', data: [
            'keywords_raw' => "ya existia\nkeyword nueva",
            'location_code' => $location->location_code,
            'language_code' => $language->language_code,
            'tags' => [],
        ])
        ->assertHasNoTableActionErrors();

    $nueva = Keyword::query()->where('keyword', 'keyword nueva')->firstOrFail();

    Queue::assertPushed(TrackKeywordsNow::class, function (TrackKeywordsNow $job) use ($nueva, $existing) {
        return $job->keywordIds === [$nueva->id]
            && ! in_array($existing->id, $job->keywordIds, true);
    });
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
