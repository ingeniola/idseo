<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * Métricas agregadas del proyecto en un punto en el tiempo, leídas
 * directamente de `project_visibility_snapshots` (calculadas sin
 * costo de API por CalculateProjectVisibility en la Fase 1, paso 8).
 * Todos los campos son null cuando todavía no existe ningún snapshot
 * en o antes de la fecha pedida — no hay que fingir un valor de 0.
 */
final class ReportPeriodMetrics
{
    public function __construct(
        public readonly ?string $asOfDate,
        public readonly ?float $visibilityScore,
        public readonly ?int $trackedKeywordsCount,
        public readonly ?int $keywordsInTop3,
        public readonly ?int $keywordsInTop10,
        public readonly ?int $keywordsInTop20,
        public readonly ?float $averagePosition,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null, null, null, null);
    }

    public function visibilityScoreDeltaFrom(self $previous): ?float
    {
        if ($this->visibilityScore === null || $previous->visibilityScore === null) {
            return null;
        }

        return round($this->visibilityScore - $previous->visibilityScore, 2);
    }
}
