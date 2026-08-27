<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\RankTracking\PostRankTrackingTasks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispara task_post de inmediato para un lote puntual de keywords, en
 * dos escenarios: (1) recién creadas (pegado masivo, alta individual,
 * importación CSV) en vez de esperar al corte diario 02:00
 * (ScheduleRankTrackingTasks) — no agrega costo nuevo, ya que
 * DueKeywordsFinder las marcaría "due" en el próximo corte de todas
 * formas, esto solo adelanta el momento; y (2) un re-chequeo manual
 * ("Rastrear ahora") fuera de la cadencia normal de la frecuencia del
 * proyecto — este SÍ es una llamada paga adicional real, deliberada
 * por quien la dispara.
 *
 * Excluye keywords que ya tienen una tarea de rastreo pendiente en
 * vuelo (mismo criterio que DueKeywordsFinder) para no duplicar el
 * gasto si alguien dispara esto dos veces seguidas (doble clic, F5).
 *
 * Recibe IDs, no modelos: se serializa en la cola, y algunas keywords
 * del lote pueden pertenecer a proyectos distintos (ej. una
 * importación CSV mal armada, o una selección multi-keyword en la
 * tabla), así que se agrupan por proyecto antes de publicar.
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
        $pendingKeywordIds = DataForSeoTask::query()
            ->where('taskable_type', Keyword::class)
            ->where('endpoint', PostRankTrackingTasks::ENDPOINT)
            ->where('status', DataForSeoTaskStatus::Pending)
            ->pluck('taskable_id');

        Keyword::query()
            ->whereIn('id', $this->keywordIds)
            ->where('is_active', true)
            ->whereNotIn('id', $pendingKeywordIds)
            ->with('project')
            ->get()
            ->groupBy('project_id')
            ->each(fn ($keywords) => $poster->execute($keywords->first()->project, $keywords));
    }
}
