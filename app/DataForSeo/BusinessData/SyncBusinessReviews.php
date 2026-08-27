<?php

declare(strict_types=1);

namespace App\DataForSeo\BusinessData;

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Models\DataForSeoTask;
use App\Models\Project;
use Illuminate\Support\Facades\URL;

/**
 * Fase 3, "Monitoreo de reseñas y Google Business Profile" (sección 5
 * del SPEC). Modo Standard (business_data/google/reviews/task_post),
 * a demanda desde la pestaña del proyecto — no hay mandato del SPEC de
 * sincronizar reseñas periódicamente, y DataForSEO cobra por cada 10
 * reseñas del `depth` solicitado (confirmado por búsqueda cruzada,
 * docs.dataforseo.com bloqueado en este entorno), así que un job
 * programado diario sin control humano podría acumular costo sin que
 * nadie lo note.
 *
 * Identifica el negocio por `place_id` (sección 4 no tiene un campo
 * para esto — se agregó `projects.google_business_place_id`, ver el
 * docblock de esa migración). Usa postback_url igual que
 * ScheduleRankTrackingTasks/TriggerSiteAudit: el payload del postback
 * se trata solo como señal de "terminado" — ProcessBusinessReviewsPostback
 * siempre pide business_data/google/reviews/task_get/{id} por
 * separado, mismo principio que ReconcilePendingTasks y el módulo de
 * auditoría on-page.
 */
class SyncBusinessReviews
{
    private const ENDPOINT = 'business_data/google/reviews/task_post';

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    public function execute(Project $project, int $depth): void
    {
        $webhookToken = (string) config('rank_tracking.webhook_token');

        $task = [
            'place_id' => $project->google_business_place_id,
            'depth' => $depth,
            'postback_url' => URL::to('/webhooks/dataforseo/reviews').'?token='.$webhookToken,
        ];

        $response = $this->client->post(self::ENDPOINT, [$task]);
        $responseTask = $response->tasks[0];

        DataForSeoTask::query()->create([
            'task_id' => $responseTask->id,
            'endpoint' => self::ENDPOINT,
            'taskable_type' => Project::class,
            'taskable_id' => $project->id,
            'status' => $responseTask->isSuccessful() ? DataForSeoTaskStatus::Pending : DataForSeoTaskStatus::Failed,
            'payload_sent' => $task,
            'cost' => $responseTask->cost,
            'posted_at' => now(),
            'error_message' => $responseTask->isSuccessful() ? null : $responseTask->statusMessage,
        ]);
    }
}
