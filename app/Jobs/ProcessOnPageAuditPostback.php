<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\DataForSeo\OnPage\Enums\AuditIssueType;
use App\Models\AuditIssue;
use App\Models\DataForSeoTask;
use App\Models\SiteAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Procesa el postback de una auditoría on-page (sección 3.3 y 5 del
 * SPEC). El payload guardado por el webhook solo se usa como señal de
 * "terminado" — nunca como fuente de los issues: siempre se piden
 * on_page/summary/{id} (GET) y on_page/pages (POST, {id, limit}) por
 * separado, verificados contra documentación pública disponible
 * (docs.dataforseo.com bloqueado en este entorno). Mismo principio que
 * ReconcilePendingTasks: confiar en una llamada propia, no en lo que
 * llegó empujado.
 */
class ProcessOnPageAuditPostback implements ShouldQueue
{
    use Queueable;

    private const SUMMARY_ENDPOINT = 'on_page/summary/';

    private const PAGES_ENDPOINT = 'on_page/pages';

    public function __construct(
        public readonly int $dataForSeoTaskId,
    ) {}

    public function handle(DataForSeoClient $client): void
    {
        $task = DataForSeoTask::query()->find($this->dataForSeoTaskId);

        if ($task === null || $task->status !== DataForSeoTaskStatus::Pending) {
            return;
        }

        if (! $task->taskable instanceof SiteAudit) {
            Log::error('site_audit.postback.unexpected_taskable', ['task_id' => $task->task_id]);

            return;
        }

        $audit = $task->taskable;

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode((string) $task->payload_received, associative: true);
        $resultTask = $payload['tasks'][0] ?? null;
        $statusCode = is_array($resultTask) ? (int) ($resultTask['status_code'] ?? 0) : 0;

        if (! is_array($resultTask) || $statusCode < 20000 || $statusCode > 20999) {
            $errorMessage = is_array($resultTask)
                ? (string) ($resultTask['status_message'] ?? 'Tarea fallida sin mensaje.')
                : 'Payload de postback sin tasks[0].';

            $task->update(['status' => DataForSeoTaskStatus::Failed, 'error_message' => $errorMessage, 'completed_at' => now()]);
            $audit->update(['status' => DataForSeoTaskStatus::Failed, 'completed_at' => now()]);

            return;
        }

        $summaryResponse = $client->get(self::SUMMARY_ENDPOINT.$task->task_id);
        $summaryTask = $summaryResponse->tasks[0];
        $summaryResult = $summaryTask->result[0] ?? null;

        $pagesResponse = $client->post(self::PAGES_ENDPOINT, [[
            'id' => $task->task_id,
            'limit' => (int) config('dataforseo.onpage_pages_fetch_limit'),
        ]]);
        $items = $pagesResponse->tasks[0]->result[0]['items'] ?? [];

        foreach ((is_array($items) ? $items : []) as $item) {
            $this->recordIssuesForPage($audit, is_array($item) ? $item : []);
        }

        $audit->update([
            'status' => DataForSeoTaskStatus::Completed,
            'completed_at' => now(),
            'pages_crawled' => is_array($summaryResult) ? ($summaryResult['crawl_status']['pages_crawled'] ?? null) : null,
            'onpage_score' => is_array($summaryResult) ? ($summaryResult['onpage_score'] ?? null) : null,
            'cost' => $summaryTask->cost,
        ]);

        $task->update([
            'status' => DataForSeoTaskStatus::Completed,
            'completed_at' => now(),
            'cost' => $summaryTask->cost,
        ]);
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function recordIssuesForPage(SiteAudit $audit, array $page): void
    {
        $url = $page['url'] ?? null;
        $checks = $page['checks'] ?? null;

        if (! is_string($url) || $url === '' || ! is_array($checks)) {
            return;
        }

        foreach (AuditIssueType::cases() as $issueType) {
            if (($checks[$issueType->value] ?? false) !== true) {
                continue;
            }

            AuditIssue::query()->create([
                'audit_id' => $audit->id,
                'url' => $url,
                'issue_type' => $issueType,
                'severity' => $issueType->severity(),
                'message' => $issueType->message(),
            ]);
        }
    }
}
