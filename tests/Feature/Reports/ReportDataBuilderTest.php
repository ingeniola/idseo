<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\ProjectVisibilitySnapshot;
use App\Models\Ranking;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Reports\ReportDataBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeReport(array $reportAttributes = [], array $templateAttributes = []): Report
{
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $template = ReportTemplate::factory()->create($templateAttributes);

    return Report::factory()->create(array_merge([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ], $reportAttributes));
}

test('metricsAsOf usa el snapshot mas reciente hasta la fecha pedida', function () {
    $report = makeReport();
    $project = $report->project;

    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-07-15', 'visibility_score' => 30.0]);
    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-08-31', 'visibility_score' => 45.5]);
    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-09-05', 'visibility_score' => 99.0]);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->currentMetrics->visibilityScore)->toBe(45.5)
        ->and($data->currentMetrics->asOfDate)->toBe('2026-08-31');
});

test('sin snapshots las metricas quedan en null, no en cero', function () {
    $report = makeReport();

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->currentMetrics->visibilityScore)->toBeNull()
        ->and($data->currentMetrics->trackedKeywordsCount)->toBeNull();
});

test('compara la visibilidad contra el snapshot del periodo anterior', function () {
    $report = makeReport(['period_start' => '2026-08-01', 'period_end' => '2026-08-31']);
    $project = $report->project;

    // Periodo anterior (julio, 31 dias) para un periodo de agosto (31 dias).
    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-07-31', 'visibility_score' => 40.0]);
    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-08-31', 'visibility_score' => 55.0]);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->previousMetrics->visibilityScore)->toBe(40.0)
        ->and($data->currentMetrics->visibilityScoreDeltaFrom($data->previousMetrics))->toBe(15.0);
});

test('arma la evolucion de visibilidad solo con snapshots dentro del periodo', function () {
    $report = makeReport(['period_start' => '2026-08-01', 'period_end' => '2026-08-31']);
    $project = $report->project;

    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-07-20', 'visibility_score' => 10.0]);
    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-08-01', 'visibility_score' => 20.0]);
    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-08-15', 'visibility_score' => 30.0]);
    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-09-01', 'visibility_score' => 40.0]);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->visibilityEvolution->pluck('date')->all())->toBe(['2026-08-01', '2026-08-15']);
});

test('calcula posicion de inicio y fin por keyword y el delta', function () {
    $report = makeReport(['period_start' => '2026-08-01', 'period_end' => '2026-08-31']);
    $project = $report->project;
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true, 'keyword' => 'zapatos']);

    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-07-25', 'position' => 20, 'url' => 'https://a.com']);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-20', 'position' => 5, 'url' => 'https://b.com']);

    $data = app(ReportDataBuilder::class)->build($report);

    $position = $data->positions->firstWhere('keyword', 'zapatos');

    expect($position->positionStart)->toBe(20)
        ->and($position->positionEnd)->toBe(5)
        ->and($position->url)->toBe('https://b.com')
        ->and($position->delta())->toBe(15);
});

test('ignora rankings posteriores al fin del periodo al calcular la posicion final', function () {
    $report = makeReport(['period_start' => '2026-08-01', 'period_end' => '2026-08-31']);
    $project = $report->project;
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-10', 'position' => 8]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-09-15', 'position' => 1]);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->positions->first()->positionEnd)->toBe(8);
});

test('excluye keywords inactivas de la tabla de posiciones', function () {
    $report = makeReport();
    $project = $report->project;
    Keyword::factory()->create(['project_id' => $project->id, 'is_active' => false]);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->positions)->toHaveCount(0);
});

test('top ganancias y top perdidas separan por signo del delta', function () {
    $report = makeReport(['period_start' => '2026-08-01', 'period_end' => '2026-08-31']);
    $project = $report->project;

    $mejora = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true, 'keyword' => 'mejora']);
    $empeora = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true, 'keyword' => 'empeora']);
    $igual = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true, 'keyword' => 'igual']);

    Ranking::factory()->create(['keyword_id' => $mejora->id, 'checked_at' => '2026-07-25', 'position' => 20]);
    Ranking::factory()->create(['keyword_id' => $mejora->id, 'checked_at' => '2026-08-20', 'position' => 5]);

    Ranking::factory()->create(['keyword_id' => $empeora->id, 'checked_at' => '2026-07-25', 'position' => 5]);
    Ranking::factory()->create(['keyword_id' => $empeora->id, 'checked_at' => '2026-08-20', 'position' => 20]);

    Ranking::factory()->create(['keyword_id' => $igual->id, 'checked_at' => '2026-07-25', 'position' => 10]);
    Ranking::factory()->create(['keyword_id' => $igual->id, 'checked_at' => '2026-08-20', 'position' => 10]);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->topGains->pluck('keyword')->all())->toBe(['mejora'])
        ->and($data->topLosses->pluck('keyword')->all())->toBe(['empeora']);
});

test('detecta keywords nuevas en top 10', function () {
    $report = makeReport(['period_start' => '2026-08-01', 'period_end' => '2026-08-31']);
    $project = $report->project;

    $entra = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true, 'keyword' => 'entra-al-top']);
    $yaEstaba = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true, 'keyword' => 'ya-estaba']);
    $sinDatoPrevio = Keyword::factory()->create(['project_id' => $project->id, 'is_active' => true, 'keyword' => 'sin-dato-previo']);

    Ranking::factory()->create(['keyword_id' => $entra->id, 'checked_at' => '2026-07-25', 'position' => 15]);
    Ranking::factory()->create(['keyword_id' => $entra->id, 'checked_at' => '2026-08-20', 'position' => 8]);

    Ranking::factory()->create(['keyword_id' => $yaEstaba->id, 'checked_at' => '2026-07-25', 'position' => 3]);
    Ranking::factory()->create(['keyword_id' => $yaEstaba->id, 'checked_at' => '2026-08-20', 'position' => 2]);

    Ranking::factory()->create(['keyword_id' => $sinDatoPrevio->id, 'checked_at' => '2026-08-20', 'position' => 9]);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->newKeywordsInTop10->pluck('keyword')->sort()->values()->all())
        ->toBe(['entra-al-top', 'sin-dato-previo']);
});

test('usa branding_overrides de la plantilla sobre el branding del cliente', function () {
    $report = makeReport(templateAttributes: ['branding_overrides' => ['primary_color' => '#ff0000']]);
    $report->project->client->update(['primary_color' => '#00ff00']);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->primaryColor)->toBe('#ff0000');
});

test('usa el color del cliente si la plantilla no tiene override', function () {
    $report = makeReport();
    $report->project->client->update(['primary_color' => '#123456']);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->primaryColor)->toBe('#123456');
});

test('arma un data URI a partir del logo del cliente en disco', function () {
    Storage::fake('local');

    $report = makeReport();
    $file = UploadedFile::fake()->image('logo.png', 10, 10);
    $path = $file->store('client-logos', 'local');
    $report->project->client->update(['logo_path' => $path]);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->logoDataUri)->toStartWith('data:image/png;base64,');
});

test('sin logo en disco el data URI es null', function () {
    $report = makeReport();
    $report->project->client->update(['logo_path' => 'client-logos/no-existe.png']);

    $data = app(ReportDataBuilder::class)->build($report);

    expect($data->logoDataUri)->toBeNull();
});
