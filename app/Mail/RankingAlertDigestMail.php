<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Project;
use App\Models\RankingAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Resumen diario de alertas para el equipo interno asignado al
 * proyecto (Fase 2, sección 5 y 6 del SPEC). A diferencia de
 * ReportGeneratedMail, este correo es interno: sí puede mencionar
 * DataForSEO/posiciones libremente, no está sujeto a la restricción de
 * "no mencionar la fuente de datos" de la sección 5.5 (esa es
 * exclusiva del portal de cliente).
 */
class RankingAlertDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, RankingAlert>  $alerts
     */
    public function __construct(
        public readonly Project $project,
        public readonly Collection $alerts,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Alertas de ranking — {$this->project->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ranking-alert-digest',
            with: [
                'projectName' => $this->project->name,
                'alerts' => $this->alerts,
            ],
        );
    }
}
