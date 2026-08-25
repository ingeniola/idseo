<?php

declare(strict_types=1);

namespace App\DataForSeo\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara tras cada respuesta HTTP recibida de DataForSEO (éxito o
 * error, transitorio o permanente). Es el punto de extensión para
 * persistir en `dataforseo_requests` y `cost_ledger` una vez existan
 * esas tablas (fases 3 y 4); por ahora no tiene listeners.
 */
final class DataForSeoRequestCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $method,
        public readonly string $endpoint,
        public readonly int $httpStatus,
        public readonly int $durationMs,
        public readonly ?int $apiStatusCode,
        public readonly ?float $cost,
    ) {}
}
