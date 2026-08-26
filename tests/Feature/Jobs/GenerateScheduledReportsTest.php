<?php

declare(strict_types=1);

use App\Jobs\GenerateReport;
use App\Jobs\GenerateScheduledReports;
use App\Jobs\SendReportEmail;
use App\Models\Client;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-01 07:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('crea un reporte para el mes calendario anterior usando la plantilla propia del cliente', function () {
    Bus::fake();

    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id, 'is_active' => true]);
    $template = ReportTemplate::factory()->create(['client_id' => $client->id]);
    ReportTemplate::factory()->create(['client_id' => null]); // plantilla global, no deberia usarse aqui

    app(GenerateScheduledReports::class)->handle();

    $report = Report::query()->where('project_id', $project->id)->first();

    expect($report)->not->toBeNull()
        ->and($report->template_id)->toBe($template->id)
        ->and($report->period_start)->toBe('2026-08-01')
        ->and($report->period_end)->toBe('2026-08-31');

    Bus::assertChained([
        new GenerateReport($report->id),
        new SendReportEmail($report->id),
    ]);
});

test('usa la plantilla global si el cliente no tiene una propia', function () {
    Bus::fake();

    $project = Project::factory()->create(['is_active' => true]);
    $global = ReportTemplate::factory()->create(['client_id' => null]);

    app(GenerateScheduledReports::class)->handle();

    $report = Report::query()->where('project_id', $project->id)->first();

    expect($report->template_id)->toBe($global->id);
});

test('sin ninguna plantilla disponible se salta el proyecto con un warning', function () {
    Bus::fake();
    Log::spy();

    Project::factory()->create(['is_active' => true]);

    app(GenerateScheduledReports::class)->handle();

    expect(Report::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once();
    Bus::assertNothingDispatched();
});

test('ignora proyectos inactivos', function () {
    Bus::fake();

    ReportTemplate::factory()->create(['client_id' => null]);
    Project::factory()->create(['is_active' => false]);

    app(GenerateScheduledReports::class)->handle();

    expect(Report::query()->count())->toBe(0);
});

test('correr el job dos veces para el mismo mes no duplica el reporte ni el envio', function () {
    Bus::fake();

    $project = Project::factory()->create(['is_active' => true]);
    ReportTemplate::factory()->create(['client_id' => null]);

    app(GenerateScheduledReports::class)->handle();
    app(GenerateScheduledReports::class)->handle();

    expect(Report::query()->where('project_id', $project->id)->count())->toBe(1);
    Bus::assertDispatchedTimes(GenerateReport::class, 1);
});
