<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\RankingAlertDigestMail;
use App\Models\Project;
use App\Models\RankingAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envío del resumen diario de alertas a los usuarios internos
 * asignados al proyecto (sección 5 y 6 del SPEC, "DetectRankingAlerts
 * ... notifica"). Un solo correo por proyecto por día con todas las
 * alertas nuevas de hoy, no un correo por alerta — evita saturar a un
 * analista con N correos si un proyecto tiene un mal día.
 *
 * Sigue el mismo patrón que SendReportEmail: recibe solo el id (no el
 * modelo serializado), lo vuelve a buscar en handle(), y valida su
 * propio estado antes de mandar nada.
 */
class SendRankingAlertDigest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $projectId,
    ) {}

    public function handle(): void
    {
        $project = Project::query()->find($this->projectId);

        if ($project === null) {
            Log::warning('alerts.digest.project_not_found', ['project_id' => $this->projectId]);

            return;
        }

        $alerts = RankingAlert::query()
            ->where('project_id', $project->id)
            ->where('triggered_at', Carbon::now()->toDateString())
            ->whereNull('notified_at')
            ->with('keyword')
            ->get();

        if ($alerts->isEmpty()) {
            return;
        }

        $recipients = $project->users()->pluck('email')->all();

        if ($recipients === []) {
            Log::warning('alerts.digest.no_recipients', ['project_id' => $project->id]);

            return;
        }

        Mail::to($recipients)->send(new RankingAlertDigestMail($project, $alerts));

        RankingAlert::query()
            ->whereIn('id', $alerts->pluck('id'))
            ->update(['notified_at' => now()]);
    }
}
