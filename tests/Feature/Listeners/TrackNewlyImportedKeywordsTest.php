<?php

declare(strict_types=1);

use App\Filament\Imports\KeywordImporter;
use App\Jobs\TrackKeywordsNow;
use App\Listeners\TrackNewlyImportedKeywords;
use App\Models\Keyword;
use App\Models\Project;
use Carbon\CarbonInterface;
use Filament\Actions\Imports\Events\ImportCompleted;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function makeImportCompletedEvent(Project $project, CarbonInterface $startedAt): ImportCompleted
{
    $import = new Import;
    $import->importer = KeywordImporter::class;
    $import->file_name = 'keywords.csv';
    $import->file_path = 'keywords.csv';
    $import->total_rows = 1;
    $import->created_at = $startedAt;

    return new ImportCompleted($import, [], ['project_id' => $project->id]);
}

test('dispara TrackKeywordsNow solo con las keywords sin rankings creadas durante este import', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $importStartedAt = now()->subMinute();

    $vieja = Keyword::factory()->create(['project_id' => $project->id, 'created_at' => now()->subHour()]);
    $nueva = Keyword::factory()->create(['project_id' => $project->id, 'created_at' => now()]);

    app(TrackNewlyImportedKeywords::class)->handle(makeImportCompletedEvent($project, $importStartedAt));

    Queue::assertPushed(TrackKeywordsNow::class, function (TrackKeywordsNow $job) use ($nueva, $vieja) {
        return $job->keywordIds === [$nueva->id]
            && ! in_array($vieja->id, $job->keywordIds, true);
    });
});

test('no dispara nada si el import no trajo keywords nuevas sin rastrear', function () {
    Queue::fake();

    $project = Project::factory()->create();

    app(TrackNewlyImportedKeywords::class)->handle(makeImportCompletedEvent($project, now()));

    Queue::assertNotPushed(TrackKeywordsNow::class);
});

test('ignora eventos de imports que no son de KeywordImporter', function () {
    Queue::fake();

    $project = Project::factory()->create();
    Keyword::factory()->create(['project_id' => $project->id, 'created_at' => now()]);

    $import = new Import;
    $import->importer = 'App\\Filament\\Imports\\OtraCosaImporter';
    $import->file_name = 'otra-cosa.csv';
    $import->file_path = 'otra-cosa.csv';
    $import->total_rows = 1;
    $import->created_at = now()->subMinute();

    app(TrackNewlyImportedKeywords::class)->handle(new ImportCompleted($import, [], ['project_id' => $project->id]));

    Queue::assertNotPushed(TrackKeywordsNow::class);
});
