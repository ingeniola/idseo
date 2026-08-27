<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job cada hora (sección 6 y 3.3 del SPEC): los postbacks se pueden
 * perder, así que recupera vía tasks_ready + task_get cualquier tarea
 * nuestra que lleve más de N horas en pending.
 *
 * tasks_ready devuelve las tareas listas de TODA la cuenta, no solo
 * las nuestras, así que se cruza contra nuestras tareas pending antes
 * de pedir el detalle de cada una con task_get.
 */
class ReconcilePendingTasks implements ShouldQueue
{
    use Queueable;

    private const TASKS_READY_ENDPOINT = 'serp/google/organic/tasks_ready';

    private const TASK_GET_ENDPOINT = 'serp/google/organic/task_get/advanced';

    public function handle(DataForSeoClient $client): void
    {
        $staleSince = now()->subHours((int) config('rank_tracking.reconcile_pending_after_hours'));

        $orphanedTaskIds = DataForSeoTask::query()
            ->where('status', DataForSeoTaskStatus::Pending)
            ->where('endpoint', 'serp/google/organic/task_post')
            ->where('posted_at', '<=', $staleSince)
            ->pluck('task_id')
            ->flip();

        if ($orphanedTaskIds->isEmpty()) {
            return;
        }

        $readyResponse = $client->get(self::TASKS_READY_ENDPOINT);

        $readyTaskIds = collect($readyResponse->tasks)
            ->flatMap(fn ($task) => $task->result ?? [])
            ->pluck('id')
            ->filter(fn ($id) => $orphanedTaskIds->has($id));

        foreach ($readyTaskIds as $taskId) {
            $this->recoverTask($client, (string) $taskId);
        }
    }

    private function recoverTask(DataForSeoClient $client, string $taskId): void
    {
        $task = DataForSeoTask::query()
            ->where('task_id', $taskId)
            ->where('status', DataForSeoTaskStatus::Pending)
            ->first();

        if ($task === null) {
            return;
        }

        $projectId = $task->taskable instanceof Keyword ? $task->taskable->project_id : null;

        try {
            $response = $client->get(self::TASK_GET_ENDPOINT.'/'.$taskId, $projectId);
        } catch (\Throwable $exception) {
            Log::warning('rank_tracking.reconcile.task_get_failed', [
                'task_id' => $taskId,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $task->update([
            'payload_received' => json_encode($response->toArray()),
        ]);

        ProcessDataForSeoPostback::dispatch($task->id);
    }
}
