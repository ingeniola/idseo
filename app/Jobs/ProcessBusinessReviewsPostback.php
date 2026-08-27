<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Models\BusinessReview;
use App\Models\DataForSeoTask;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Procesa el postback de una sincronización de reseñas (sección 3.3 y
 * 5 del SPEC). Igual que ProcessOnPageAuditPostback: el payload del
 * webhook solo se usa como señal de "terminado", nunca como fuente de
 * las reseñas — siempre se pide business_data/google/reviews/task_get/{id}
 * por separado, verificado contra documentación pública disponible
 * (docs.dataforseo.com bloqueado en este entorno).
 */
class ProcessBusinessReviewsPostback implements ShouldQueue
{
    use Queueable;

    private const TASK_GET_ENDPOINT = 'business_data/google/reviews/task_get/';

    public function __construct(
        public readonly int $dataForSeoTaskId,
    ) {}

    public function handle(DataForSeoClient $client): void
    {
        $task = DataForSeoTask::query()->find($this->dataForSeoTaskId);

        if ($task === null || $task->status !== DataForSeoTaskStatus::Pending) {
            return;
        }

        if (! $task->taskable instanceof Project) {
            Log::error('business_reviews.postback.unexpected_taskable', ['task_id' => $task->task_id]);

            return;
        }

        $project = $task->taskable;

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode((string) $task->payload_received, associative: true);
        $resultTask = $payload['tasks'][0] ?? null;
        $statusCode = is_array($resultTask) ? (int) ($resultTask['status_code'] ?? 0) : 0;

        if (! is_array($resultTask) || $statusCode < 20000 || $statusCode > 20999) {
            $errorMessage = is_array($resultTask)
                ? (string) ($resultTask['status_message'] ?? 'Tarea fallida sin mensaje.')
                : 'Payload de postback sin tasks[0].';

            $task->update(['status' => DataForSeoTaskStatus::Failed, 'error_message' => $errorMessage, 'completed_at' => now()]);

            return;
        }

        $response = $client->get(self::TASK_GET_ENDPOINT.$task->task_id, $project->id);
        $resultData = $response->tasks[0]->result[0] ?? null;
        $items = is_array($resultData) ? ($resultData['items'] ?? []) : [];

        foreach ((is_array($items) ? $items : []) as $item) {
            $this->upsertReview($project, is_array($item) ? $item : []);
        }

        $task->update([
            'status' => DataForSeoTaskStatus::Completed,
            'completed_at' => now(),
            'cost' => $response->tasks[0]->cost,
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function upsertReview(Project $project, array $item): void
    {
        $reviewId = $item['review_id'] ?? null;

        if (! is_string($reviewId) || $reviewId === '') {
            return;
        }

        $rating = $item['rating']['value'] ?? null;
        $ownerAnswer = $item['owner_answer'] ?? null;

        BusinessReview::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'review_id' => $reviewId,
            ],
            [
                'reviewer_name' => $item['profile_name'] ?? null,
                'profile_image_url' => $item['profile_image_url'] ?? null,
                'rating' => is_numeric($rating) ? (int) $rating : null,
                'review_text' => $item['review_text'] ?? null,
                'published_at' => isset($item['timestamp']) ? Carbon::parse($item['timestamp']) : null,
                'owner_answer' => filled($ownerAnswer) ? $ownerAnswer : null,
                'owner_answered_at' => isset($item['owner_timestamp']) ? Carbon::parse($item['owner_timestamp']) : null,
                'is_local_guide' => (bool) ($item['local_guide'] ?? false),
                'raw' => $item,
            ],
        );
    }
}
