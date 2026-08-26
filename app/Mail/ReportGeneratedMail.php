<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo al contacto del cliente con el reporte adjunto (sección 5.4
 * del SPEC: "Envío por correo al contacto del cliente"). El cuerpo no
 * menciona DataForSEO ni ningún proveedor de datos, en el mismo
 * espíritu de "no mencionar la fuente de datos" de la sección 5.5.
 */
class ReportGeneratedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Report $report,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reporte SEO — {$this->report->project->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.report-generated',
            with: [
                'projectName' => $this->report->project->name,
                'periodStart' => $this->report->period_start,
                'periodEnd' => $this->report->period_end,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', (string) $this->report->file_path)
                ->as("reporte-{$this->report->project->name}-{$this->report->period_start}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
