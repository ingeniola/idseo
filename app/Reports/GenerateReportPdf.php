<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Genera el PDF de un Report (sección 5.4 del SPEC): arma los datos,
 * renderiza la plantilla Blade a HTML, y le pide al PdfRenderer que lo
 * convierta a PDF. Un fallo deja el Report en Failed y NO relanza la
 * excepción — igual que ReconcilePendingTasks y ProcessDataForSeoPostback,
 * un reporte fallido se reintenta generándolo de nuevo manualmente
 * desde el panel, no por el mecanismo de reintento de colas.
 */
class GenerateReportPdf
{
    private const DISK = 'local';

    public function __construct(
        private readonly ReportDataBuilder $dataBuilder,
        private readonly PdfRenderer $renderer,
    ) {}

    public function execute(Report $report): void
    {
        $report->update(['status' => ReportStatus::Generating]);

        try {
            $data = $this->dataBuilder->build($report);
            $html = view('reports.pdf', ['data' => $data])->render();

            $disk = Storage::disk(self::DISK);
            $relativePath = "reports/{$report->project_id}/report-{$report->id}.pdf";
            $disk->makeDirectory("reports/{$report->project_id}");

            $this->renderer->render($html, $disk->path($relativePath));

            $report->update([
                'status' => ReportStatus::Completed,
                'file_path' => $relativePath,
            ]);
        } catch (Throwable $exception) {
            Log::error('reports.generate.failed', [
                'report_id' => $report->id,
                'message' => $exception->getMessage(),
            ]);

            $report->update(['status' => ReportStatus::Failed]);
        }
    }
}
