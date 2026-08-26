<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\Models\Ranking;
use App\Models\SerpSnapshot;
use App\RankTracking\SerpResultParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Procesa el payload guardado por el webhook (sección 3.3 del SPEC):
 * escribe rankings y snapshot. Usa updateOrCreate sobre
 * (keyword_id, checked_at) para que un reintento del job (o el mismo
 * postback llegando dos veces antes de que se alcance a marcar el
 * task como completado) no duplique la fila de ranking.
 */
class ProcessDataForSeoPostback implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $dataForSeoTaskId,
    ) {}

    public function handle(): void
    {
        $task = DataForSeoTask::query()->find($this->dataForSeoTaskId);

        if ($task === null || $task->status !== DataForSeoTaskStatus::Pending) {
            return;
        }

        if (! $task->taskable instanceof Keyword) {
            Log::error('rank_tracking.postback.unexpected_taskable', ['task_id' => $task->task_id]);

            return;
        }

        $keyword = $task->taskable;

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode((string) $task->payload_received, associative: true);
        $resultTask = $payload['tasks'][0] ?? null;

        if (! is_array($resultTask)) {
            $task->update(['status' => DataForSeoTaskStatus::Failed, 'error_message' => 'Payload de postback sin tasks[0].', 'completed_at' => now()]);

            return;
        }

        $statusCode = (int) ($resultTask['status_code'] ?? 0);

        if ($statusCode < 20000 || $statusCode > 20999) {
            $task->update([
                'status' => DataForSeoTaskStatus::Failed,
                'error_message' => (string) ($resultTask['status_message'] ?? 'Tarea fallida sin mensaje.'),
                'completed_at' => now(),
                'cost' => $resultTask['cost'] ?? $task->cost,
            ]);

            return;
        }

        $resultPage = $resultTask['result'][0] ?? null;
        $items = is_array($resultPage) ? ($resultPage['items'] ?? []) : [];

        $parser = new SerpResultParser(is_array($items) ? $items : [], $keyword->project->domain);

        $previousRanking = $keyword->rankings()->orderByDesc('checked_at')->first();
        $organic = $parser->findOrganicRanking();

        Ranking::query()->updateOrCreate(
            [
                'keyword_id' => $keyword->id,
                'checked_at' => Carbon::now()->toDateString(),
            ],
            [
                'position' => $organic['position'] ?? null,
                'previous_position' => $previousRanking?->position,
                'url' => $organic['url'] ?? null,
                'serp_features' => $parser->detectedFeatures(),
                'estimated_traffic' => null,
                'is_featured_snippet' => $parser->hasFeaturedSnippet(),
                'is_local_pack' => $parser->hasLocalPack(),
            ],
        );

        SerpSnapshot::query()->create([
            'keyword_id' => $keyword->id,
            'captured_at' => now(),
            'raw_response' => is_array($resultPage) ? $resultPage : [],
            'top_results' => collect(is_array($items) ? $items : [])
                ->filter(fn (array $item) => ($item['type'] ?? null) === 'organic')
                ->take(10)
                ->values()
                ->all(),
        ]);

        $task->update([
            'status' => DataForSeoTaskStatus::Completed,
            'completed_at' => now(),
            'cost' => $resultTask['cost'] ?? $task->cost,
        ]);
    }
}
