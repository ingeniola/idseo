<?php

declare(strict_types=1);

namespace App\Listeners;

use App\DataForSeo\Events\DataForSeoRequestCompleted;
use App\Models\DataForSeoRequestLog;

/**
 * Persiste absolutamente todo intento de llamada a DataForSEO en
 * `dataforseo_requests`, incluyendo fallos de conexión pura (sección
 * 3.4 del SPEC: "Registrar todo request y response... Sin excepción").
 * A diferencia de RecordDataForSeoCost, no filtra por api_status_code.
 */
class RecordDataForSeoRequestLog
{
    public function handle(DataForSeoRequestCompleted $event): void
    {
        DataForSeoRequestLog::query()->create([
            'method' => $event->method,
            'endpoint' => $event->endpoint,
            'duration_ms' => $event->durationMs,
            'http_status' => $event->httpStatus,
            'api_status_code' => $event->apiStatusCode,
            'cost' => $event->cost,
        ]);
    }
}
