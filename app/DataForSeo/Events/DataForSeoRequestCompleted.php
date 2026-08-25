<?php

declare(strict_types=1);

namespace App\DataForSeo\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara tras cada intento de llamada a DataForSEO: éxito, error
 * transitorio o permanente, o incluso un fallo de conexión puro (en ese
 * caso httpStatus/apiStatusCode/cost llegan null). Tiene dos listeners:
 * RecordDataForSeoRequestLog (persiste en `dataforseo_requests`, la
 * auditoría "sin excepción" de la sección 3.4 del SPEC) y
 * RecordDataForSeoCost (persiste en `cost_ledger` cuando sí hubo
 * status_code parseable).
 */
final class DataForSeoRequestCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $method,
        public readonly string $endpoint,
        public readonly ?int $httpStatus,
        public readonly int $durationMs,
        public readonly ?int $apiStatusCode,
        public readonly ?float $cost,
    ) {}
}
