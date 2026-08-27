<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Keyword;
use App\RankTracking\PostRankTrackingTasks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispara task_post de inmediato para un lote puntual de keywords
 * (recién creadas por pegado masivo, alta individual o importación
 * CSV) en vez de esperar al corte diario 02:00
 * (ScheduleRankTrackingTasks). No agrega costo nuevo: estas keywords
 * nunca se han rastreado, así que DueKeywordsFinder ya las marcaría
 * "due" en el próximo corte de todas formas — esto solo adelanta el
 * momento en que se cobra, no lo duplica (la keyword queda con
 * checked_at de hoy, y no vuelve a ser "due" hasta que pase el
 * intervalo de la frecuencia del proyecto).
 *
 * Recibe IDs, no modelos: se serializa en la cola, y algunas keywords
 * del lote pueden pertenecer a proyectos distintos (ej. una
 * importación CSV mal armada), así que se agrupan por proyecto antes
 * de publicar.
 */
class TrackKeywordsNow implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, int>  $keywordIds
     */
    public function __construct(
        public readonly array $keywordIds,
    ) {}

    public function handle(PostRankTrackingTasks $poster): void
    {
        Keyword::query()
            ->whereIn('id', $this->keywordIds)
            ->where('is_active', true)
            ->with('project')
            ->get()
            ->groupBy('project_id')
            ->each(fn ($keywords) => $poster->execute($keywords->first()->project, $keywords));
    }
}
