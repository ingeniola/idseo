<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Audit\AuditLogger;
use App\Enums\AuditEvent;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de un reporte ya generado, compartida entre el panel
 * interno y el portal de cliente (Fase 1, paso 11): un usuario interno
 * puede descargar cualquier reporte, uno del portal solo los de
 * proyectos de su propio cliente — ver ReportPolicy::view(). Una sola
 * ruta con autorización por Policy es más simple que mantener dos
 * endpoints de descarga idénticos con reglas de acceso distintas.
 */
class DownloadReportController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function __invoke(Request $request, Report $report): StreamedResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user?->can('view', $report)) {
            $this->auditLogger->log(
                AuditEvent::AuthorizationDenied,
                user: $user,
                context: ['reason' => 'cannot_view_report', 'report_id' => $report->id],
            );

            abort(403);
        }

        abort_unless($report->status === ReportStatus::Completed && filled($report->file_path), 404);

        $this->auditLogger->log(AuditEvent::ReportDownloaded, user: $user, context: ['report_id' => $report->id]);

        $fileName = "reporte-{$report->project->name}-{$report->period_start}.pdf";

        return Storage::disk('local')->download($report->file_path, $fileName);
    }
}
