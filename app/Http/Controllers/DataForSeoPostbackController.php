<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Jobs\ProcessDataForSeoPostback;
use App\Jobs\ProcessOnPageAuditPostback;
use App\Models\DataForSeoTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recibe el postback de DataForSEO (sección 3.3 del SPEC). No procesa:
 * valida token + que el task_id exista y esté pending, guarda el
 * payload crudo y despacha un job. Debe responder 200 en menos de
 * 500ms. Idempotente: si llega dos veces el mismo task_id, la segunda
 * vez ya no está en estado pending y se ignora sin error.
 *
 * DataForSEO manda el resultado comprimido en gzip (confirmado en la
 * documentación pública), así que hay que descomprimir el body crudo
 * antes de decodificar JSON — Laravel no lo hace solo.
 *
 * `$type` (el segmento de la URL, ej. /webhooks/dataforseo/serp) elige
 * el job de procesamiento: cada familia de endpoint tiene su propia
 * forma de resultado y su propio taskable, así que no tiene sentido
 * que un solo job procese todos los tipos. El token de seguridad es el
 * mismo para todos los tipos (rank_tracking.webhook_token): es un
 * secreto compartido del webhook, no algo específico de rank tracking
 * a pesar del nombre de la config (heredado de cuando solo existía
 * SERP).
 */
class DataForSeoPostbackController extends Controller
{
    private const JOBS = [
        'serp' => ProcessDataForSeoPostback::class,
        'onpage' => ProcessOnPageAuditPostback::class,
    ];

    public function __invoke(Request $request, string $type): JsonResponse
    {
        if (! hash_equals((string) config('rank_tracking.webhook_token'), (string) $request->query('token'))) {
            abort(403);
        }

        $job = self::JOBS[$type] ?? null;

        if ($job === null) {
            Log::warning('dataforseo.postback.unknown_type', ['type' => $type]);

            return response()->json(['status' => 'unknown_type'], 404);
        }

        $payload = $this->decodePayload($request);

        if ($payload === null) {
            Log::warning('rank_tracking.postback.invalid_payload', ['type' => $type]);

            return response()->json(['status' => 'invalid_payload'], 400);
        }

        $taskId = $payload['tasks'][0]['id'] ?? null;

        if (! is_string($taskId)) {
            Log::warning('rank_tracking.postback.missing_task_id', ['type' => $type]);

            return response()->json(['status' => 'missing_task_id'], 400);
        }

        $task = DataForSeoTask::query()->where('task_id', $taskId)->first();

        if ($task === null) {
            Log::warning('rank_tracking.postback.unknown_task', ['task_id' => $taskId]);

            return response()->json(['status' => 'unknown_task'], 404);
        }

        if ($task->status !== DataForSeoTaskStatus::Pending) {
            // Ya procesado (postback duplicado) o falló antes: idempotente, no hacer nada.
            return response()->json(['status' => 'already_processed']);
        }

        $task->update(['payload_received' => json_encode($payload)]);

        $job::dispatch($task->id);

        return response()->json(['status' => 'accepted']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(Request $request): ?array
    {
        $body = $request->getContent();

        if ($request->header('Content-Encoding') === 'gzip' || $this->looksGzipped($body)) {
            $decoded = @gzdecode($body);

            if ($decoded !== false) {
                $body = $decoded;
            }
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, associative: true);

        return is_array($data) ? $data : null;
    }

    private function looksGzipped(string $body): bool
    {
        return strlen($body) >= 2 && bin2hex(substr($body, 0, 2)) === '1f8b';
    }
}
