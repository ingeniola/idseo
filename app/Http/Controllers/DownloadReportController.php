<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga interna de un reporte ya generado. El portal de cliente
 * (Fase 1, paso 11) tendrá su propia ruta de descarga con su propia
 * autorización — esta es solo para el equipo de Ingenio desde el
 * panel Filament, por eso basta con isInternal() y no hace falta una
 * Policy todavía (el "endurecimiento" de autorización es la Fase 1,
 * paso 12).
 */
class DownloadReportController extends Controller
{
    public function __invoke(Request $request, Report $report): StreamedResponse
    {
        abort_unless($request->user()?->role->isInternal(), 403);
        abort_unless($report->status === ReportStatus::Completed && filled($report->file_path), 404);

        $fileName = "reporte-{$report->project->name}-{$report->period_start}.pdf";

        return Storage::disk('local')->download($report->file_path, $fileName);
    }
}
