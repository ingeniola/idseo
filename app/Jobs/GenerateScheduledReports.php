<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Job día 1 de cada mes (sección 6 del SPEC): genera y envía el
 * reporte del mes anterior para cada proyecto activo.
 *
 * Elige la plantilla propia del cliente del proyecto
 * (report_templates.client_id === projects.client_id) o, si no
 * existe, la plantilla global (client_id null) — sección 5.4:
 * "client_id (nullable = plantilla global)". Un proyecto sin ninguna
 * de las dos se salta con un warning en vez de fallar todo el job.
 *
 * firstOrCreate sobre la llave única (project_id, template_id,
 * period_start, period_end) — ver la migración
 * reports_project_template_period_unique — hace que correr este job
 * dos veces para el mismo mes no duplique el reporte ni reenvíe el
 * correo.
 */
class GenerateScheduledReports implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $periodStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $periodEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        Project::query()
            ->where('is_active', true)
            ->each(function (Project $project) use ($periodStart, $periodEnd) {
                $template = $this->templateFor($project);

                if ($template === null) {
                    Log::warning('reports.scheduled.no_template', ['project_id' => $project->id]);

                    return;
                }

                $report = Report::query()->firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'template_id' => $template->id,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                    ],
                    ['status' => ReportStatus::Pending],
                );

                if (! $report->wasRecentlyCreated) {
                    return;
                }

                Bus::chain([
                    new GenerateReport($report->id),
                    new SendReportEmail($report->id),
                ])->dispatch();
            });
    }

    private function templateFor(Project $project): ?ReportTemplate
    {
        return ReportTemplate::query()->where('client_id', $project->client_id)->first()
            ?? ReportTemplate::query()->whereNull('client_id')->first();
    }
}
