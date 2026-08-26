<?php

declare(strict_types=1);

use App\Filament\Imports\KeywordImporter;
use App\Models\Keyword;
use App\Models\Project;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeKeywordImporter(Project $project): KeywordImporter
{
    $import = new Import;
    $import->file_name = 'keywords.csv';
    $import->file_path = 'keywords.csv';
    $import->importer = KeywordImporter::class;
    $import->total_rows = 1;

    $columnMap = [
        'keyword' => 'keyword',
        'location_code' => 'location_code',
        'language_code' => 'language_code',
        'tags' => 'tags',
    ];

    return new KeywordImporter($import, $columnMap, ['project_id' => $project->id]);
}

test('crea una keyword nueva desde una fila de CSV, con tags separadas por coma', function () {
    $project = Project::factory()->create();
    $importer = makeKeywordImporter($project);

    $importer([
        'keyword' => 'zapatos deportivos',
        'location_code' => '2340',
        'language_code' => 'es',
        'tags' => 'marca, transaccional',
    ]);

    $keyword = Keyword::query()->where('project_id', $project->id)->first();

    expect($keyword)->not->toBeNull()
        ->and($keyword->keyword)->toBe('zapatos deportivos')
        ->and($keyword->location_code)->toBe(2340)
        ->and($keyword->language_code)->toBe('es')
        ->and($keyword->tags)->toBe(['marca', 'transaccional']);
});

test('una fila que ya existe (mismo project+keyword+ubicacion+idioma) actualiza en vez de duplicar', function () {
    $project = Project::factory()->create();

    $existing = Keyword::factory()->create([
        'project_id' => $project->id,
        'keyword' => 'zapatos deportivos',
        'location_code' => 2340,
        'language_code' => 'es',
        'tags' => [],
    ]);

    $importer = makeKeywordImporter($project);

    $importer([
        'keyword' => 'zapatos deportivos',
        'location_code' => '2340',
        'language_code' => 'es',
        'tags' => 'marca',
    ]);

    expect(Keyword::query()->where('project_id', $project->id)->count())->toBe(1);

    expect($existing->fresh()->tags)->toBe(['marca']);
});
