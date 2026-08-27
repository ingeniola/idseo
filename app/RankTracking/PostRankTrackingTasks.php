<?php

declare(strict_types=1);

namespace App\RankTracking;

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Publica tareas serp/google/organic/task_post (modo Standard, sección
 * 3.2 del SPEC) para un lote de keywords de un proyecto, con
 * postback_url para recibir el resultado sin hacer polling (sección
 * 3.3). Extraído de ScheduleRankTrackingTasks (el job diario 02:00)
 * para reutilizarlo también cuando se agregan keywords nuevas fuera
 * de ese horario — sección 6 del SPEC: rastrear una keyword nueva no
 * debería esperar hasta el próximo corte diario.
 */
class PostRankTrackingTasks
{
    public const ENDPOINT = 'serp/google/organic/task_post';

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    /**
     * @param  Collection<int, Keyword>  $keywords
     */
    public function execute(Project $project, Collection $keywords): void
    {
        if ($keywords->isEmpty()) {
            return;
        }

        $webhookToken = config('rank_tracking.webhook_token');

        if (blank($webhookToken)) {
            Log::error('rank_tracking.schedule.missing_webhook_token', [
                'message' => 'DATAFORSEO_WEBHOOK_TOKEN no está configurado; no se programan tareas para no dejar el webhook sin protección.',
            ]);

            return;
        }

        foreach ($keywords->chunk((int) config('dataforseo.max_tasks_per_request')) as $chunk) {
            $this->postChunk($project, $chunk, (string) $webhookToken);
        }
    }

    /**
     * @param  Collection<int, Keyword>  $chunk
     */
    private function postChunk(Project $project, Collection $chunk, string $webhookToken): void
    {
        $keywords = $chunk->values();

        $tasks = $keywords->map(fn (Keyword $keyword) => [
            'keyword' => $keyword->keyword,
            'location_code' => $keyword->location_code,
            'language_code' => $keyword->language_code,
            'device' => 'desktop',
            'postback_url' => $this->postbackUrl($webhookToken),
            'postback_data' => config('rank_tracking.postback_data'),
        ])->all();

        try {
            $response = $this->client->post(self::ENDPOINT, $tasks, $project->id);
        } catch (DataForSeoBudgetExceededException $exception) {
            Log::warning('rank_tracking.schedule.budget_exceeded', [
                'project_id' => $project->id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($response->tasks as $index => $responseTask) {
            $keyword = $keywords->get($index);

            if ($keyword === null) {
                continue;
            }

            DataForSeoTask::query()->create([
                'task_id' => $responseTask->id,
                'endpoint' => self::ENDPOINT,
                'taskable_type' => Keyword::class,
                'taskable_id' => $keyword->id,
                'status' => $responseTask->isSuccessful()
                    ? DataForSeoTaskStatus::Pending
                    : DataForSeoTaskStatus::Failed,
                'payload_sent' => $tasks[$index],
                'cost' => $responseTask->cost,
                'posted_at' => now(),
                'error_message' => $responseTask->isSuccessful() ? null : $responseTask->statusMessage,
            ]);
        }
    }

    private function postbackUrl(string $webhookToken): string
    {
        return URL::to('/webhooks/dataforseo/serp').'?token='.$webhookToken;
    }
}
