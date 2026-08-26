<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Mail\ReportGeneratedMail;
use App\Models\Report;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envío por correo al contacto del cliente (sección 5.4 del SPEC).
 * Requiere que el Report ya esté Completed con un file_path — si se
 * encadena después de GenerateReport (Bus::chain) y ese job falló,
 * la cadena ya se detuvo antes de llegar aquí; esta comprobación
 * cubre el caso de que alguien dispare este job suelto desde el panel
 * sobre un reporte que no está listo.
 */
class SendReportEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $reportId,
    ) {}

    public function handle(): void
    {
        $report = Report::query()->find($this->reportId);

        if ($report === null || $report->status !== ReportStatus::Completed || blank($report->file_path)) {
            Log::warning('reports.send.not_ready', ['report_id' => $this->reportId]);

            return;
        }

        $client = $report->project->client;

        if (blank($client->contact_email)) {
            Log::warning('reports.send.missing_contact_email', ['report_id' => $report->id, 'client_id' => $client->id]);

            return;
        }

        Mail::to($client->contact_email)->send(new ReportGeneratedMail($report));

        $report->update(['sent_at' => now()]);
    }
}
