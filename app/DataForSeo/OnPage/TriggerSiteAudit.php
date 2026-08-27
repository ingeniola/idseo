<?php

declare(strict_types=1);

namespace App\DataForSeo\OnPage;

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Models\DataForSeoTask;
use App\Models\Project;
use App\Models\SiteAudit;
use Illuminate\Support\Facades\URL;

/**
 * Fase 3, "Auditoría técnica on-page" (sección 5 del SPEC). Modo
 * Standard (on_page/task_post) porque una auditoría de sitio completa
 * puede tardar minutos u horas en crawlear — no es una consulta
 * interactiva Live como Backlinks. Se dispara a demanda desde la
 * pestaña del proyecto (sección 3.6: "no aplica" cache, "bajo
 * demanda"), nunca en un job programado, porque no hay mandato del
 * SPEC de auditar sitios periódicamente y es una operación costosa
 * (paga por página crawleada).
 *
 * Usa postback_url igual que ScheduleRankTrackingTasks (sección 3.3):
 * DataForSEO también acepta postback_url en on_page/task_post (además
 * de pingback_url, que solo notifica sin resultados — confirmado por
 * búsqueda cruzada, docs.dataforseo.com bloqueado en este entorno),
 * así que reutiliza el mismo mecanismo de webhook con gzip+JSON que ya
 * existe para SERP en vez de construir un receptor de pingback nuevo.
 *
 * El payload del postback de on_page se trata solo como señal de
 * "terminado", nunca como fuente de los resultados: ProcessOnPageAuditPostback
 * siempre pide on_page/summary y on_page/pages por separado
 * (mismo principio que ReconcilePendingTasks: verificar con una
 * llamada propia en vez de confiar ciegamente en lo que llegó empujado).
 */
class TriggerSiteAudit
{
    private const ENDPOINT = 'on_page/task_post';

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    public function execute(Project $project, int $maxCrawlPages): SiteAudit
    {
        $webhookToken = (string) config('rank_tracking.webhook_token');

        $task = [
            'target' => $project->domain,
            'max_crawl_pages' => $maxCrawlPages,
            'postback_url' => URL::to('/webhooks/dataforseo/onpage').'?token='.$webhookToken,
        ];

        $response = $this->client->post(self::ENDPOINT, [$task]);
        $responseTask = $response->tasks[0];

        $audit = SiteAudit::query()->create([
            'project_id' => $project->id,
            'task_id' => $responseTask->id,
            'status' => $responseTask->isSuccessful() ? DataForSeoTaskStatus::Pending : DataForSeoTaskStatus::Failed,
            'started_at' => now(),
            'cost' => $responseTask->cost,
        ]);

        DataForSeoTask::query()->create([
            'task_id' => $responseTask->id,
            'endpoint' => self::ENDPOINT,
            'taskable_type' => SiteAudit::class,
            'taskable_id' => $audit->id,
            'status' => $audit->status,
            'payload_sent' => $task,
            'cost' => $responseTask->cost,
            'posted_at' => now(),
            'error_message' => $responseTask->isSuccessful() ? null : $responseTask->statusMessage,
        ]);

        return $audit;
    }
}
