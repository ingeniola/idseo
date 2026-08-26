<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Reports\GenerateReportPdf;
use App\Reports\PdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

class FakePdfRenderer implements PdfRenderer
{
    public ?string $lastHtml = null;

    public ?string $lastPath = null;

    public bool $shouldThrow = false;

    public function render(string $html, string $absoluteOutputPath): void
    {
        if ($this->shouldThrow) {
            throw new RuntimeException('Fallo simulado de Chromium.');
        }

        $this->lastHtml = $html;
        $this->lastPath = $absoluteOutputPath;

        file_put_contents($absoluteOutputPath, '%PDF-1.4 contenido simulado');
    }
}

function makeCompleteReport(): Report
{
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $template = ReportTemplate::factory()->create();

    return Report::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
}

test('genera el PDF, guarda el file_path relativo y marca el reporte como completado', function () {
    Storage::fake('local');

    $fake = new FakePdfRenderer;
    $this->app->instance(PdfRenderer::class, $fake);

    $report = makeCompleteReport();

    app(GenerateReportPdf::class)->execute($report);

    $report->refresh();

    expect($report->status)->toBe(ReportStatus::Completed)
        ->and($report->file_path)->toBe("reports/{$report->project_id}/report-{$report->id}.pdf");

    Storage::disk('local')->assertExists($report->file_path);
    expect($fake->lastHtml)->toContain($report->project->name);
});

test('un fallo del renderer deja el reporte en failed sin lanzar la excepcion', function () {
    Storage::fake('local');

    $fake = new FakePdfRenderer;
    $fake->shouldThrow = true;
    $this->app->instance(PdfRenderer::class, $fake);

    $report = makeCompleteReport();

    app(GenerateReportPdf::class)->execute($report);

    expect($report->fresh()->status)->toBe(ReportStatus::Failed)
        ->and($report->fresh()->file_path)->toBeNull();
});

test(
    'genera un PDF real con Browsershot cuando hay un Chrome configurado',
    function () {
        Storage::fake('local');

        $report = makeCompleteReport();

        app(GenerateReportPdf::class)->execute($report);

        $report->refresh();

        expect($report->status)->toBe(ReportStatus::Completed);

        $absolutePath = Storage::disk('local')->path($report->file_path);
        expect(file_exists($absolutePath))->toBeTrue()
            ->and(filesize($absolutePath))->toBeGreaterThan(100);
        expect(file_get_contents($absolutePath, length: 4))->toBe('%PDF');
    },
)->skip(
    fn () => ! is_string(config('browsershot.chrome_path'))
        || ! is_executable((string) config('browsershot.chrome_path')),
    'Requiere BROWSERSHOT_CHROME_PATH apuntando a un binario de Chrome/Chromium real.',
);
