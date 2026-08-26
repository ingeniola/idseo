<?php

declare(strict_types=1);

use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Ejercita la misma lógica que la acción "Pegado masivo" del
 * KeywordsRelationManager (parseo de líneas + firstOrCreate),
 * directamente sobre el modelo, sin pasar por Livewire.
 */
test('el pegado masivo agrega keywords nuevas y omite las que ya existen sin duplicar', function () {
    $project = Project::factory()->create();

    Keyword::factory()->create([
        'project_id' => $project->id,
        'keyword' => 'zapatos deportivos',
        'location_code' => 2340,
        'language_code' => 'es',
    ]);

    $raw = "zapatos deportivos\nzapatos para correr\n\nzapatos para correr\ntenis para gimnasio";

    $lines = collect(preg_split('/\r\n|\r|\n/', $raw))
        ->map(fn (string $line) => trim($line))
        ->filter()
        ->unique()
        ->values();

    expect($lines)->toHaveCount(3);

    $created = 0;

    foreach ($lines as $line) {
        $keyword = $project->keywords()->firstOrCreate([
            'keyword' => $line,
            'location_code' => 2340,
            'language_code' => 'es',
        ], [
            'tags' => ['prueba'],
            'is_active' => true,
        ]);

        if ($keyword->wasRecentlyCreated) {
            $created++;
        }
    }

    expect($created)->toBe(2)
        ->and($project->keywords()->count())->toBe(3);
});
