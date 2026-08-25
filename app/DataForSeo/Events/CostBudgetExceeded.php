<?php

declare(strict_types=1);

namespace App\DataForSeo\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara cuando el circuit breaker de presupuesto se activa. Punto
 * de extensión para notificaciones (correo/Slack) en fases posteriores;
 * por ahora solo se registra en el log como crítico.
 */
final class CostBudgetExceeded
{
    use Dispatchable;

    public function __construct(
        public readonly string $reason,
        public readonly float $monthSpend,
        public readonly float $budget,
    ) {}
}
