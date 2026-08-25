<?php

declare(strict_types=1);

use App\Enums\SearchEngine;
use App\Enums\TargetType;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\Ranking;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un proyecto pertenece a un cliente y castea sus enums', function () {
    $project = Project::factory()->create();

    expect($project->client)->toBeInstanceOf(Client::class)
        ->and($project->target_type)->toBe(TargetType::Domain)
        ->and($project->search_engine)->toBe(SearchEngine::Google);
});

test('no se puede eliminar un cliente que todavia tiene proyectos', function () {
    $project = Project::factory()->create();

    expect(fn () => $project->client->delete())->toThrow(QueryException::class);
});

test('el indice unico de keywords impide duplicar keyword+ubicacion+idioma en el mismo proyecto', function () {
    $project = Project::factory()->create();

    Keyword::factory()->create([
        'project_id' => $project->id,
        'keyword' => 'zapatos deportivos',
        'location_code' => 2340,
        'language_code' => 'es',
    ]);

    expect(fn () => Keyword::factory()->create([
        'project_id' => $project->id,
        'keyword' => 'zapatos deportivos',
        'location_code' => 2340,
        'language_code' => 'es',
    ]))->toThrow(QueryException::class);
});

test('la misma keyword se puede repetir en otra ubicacion del mismo proyecto', function () {
    $project = Project::factory()->create();

    Keyword::factory()->create([
        'project_id' => $project->id,
        'keyword' => 'zapatos deportivos',
        'location_code' => 2340,
        'language_code' => 'es',
    ]);

    $second = Keyword::factory()->create([
        'project_id' => $project->id,
        'keyword' => 'zapatos deportivos',
        'location_code' => 20174,
        'language_code' => 'es',
    ]);

    expect($second->exists)->toBeTrue();
});

test('un ranking pertenece a su keyword', function () {
    $keyword = Keyword::factory()->create();
    $ranking = Ranking::factory()->create(['keyword_id' => $keyword->id]);

    expect($ranking->keyword->is($keyword))->toBeTrue();
});

test('un usuario castea su rol y puede pertenecer a un cliente', function () {
    $client = Client::factory()->create();

    $user = User::factory()->create([
        'role' => UserRole::Client,
        'client_id' => $client->id,
    ]);

    expect($user->role)->toBe(UserRole::Client)
        ->and($user->client->is($client))->toBeTrue();
});

test('un usuario nuevo por default es analyst', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Analyst);
});

test('un reporte pertenece a un proyecto y a una plantilla', function () {
    $template = ReportTemplate::factory()->create();
    $report = Report::factory()->create(['template_id' => $template->id]);

    expect($report->template->is($template))->toBeTrue()
        ->and($report->project)->toBeInstanceOf(Project::class);
});
