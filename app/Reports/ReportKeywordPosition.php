<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * Posición de una keyword al inicio y al final del período del
 * reporte, para las secciones "tabla de posiciones", "top ganancias",
 * "top pérdidas" y "keywords nuevas en top 10" (sección 5.4 del SPEC).
 */
final class ReportKeywordPosition
{
    public function __construct(
        public readonly string $keyword,
        public readonly ?int $positionStart,
        public readonly ?int $positionEnd,
        public readonly ?string $url,
        public readonly ?int $searchVolume,
    ) {}

    /**
     * Positivo = mejoró (subió posiciones), negativo = empeoró. Null
     * si falta la posición de inicio o de fin del período.
     */
    public function delta(): ?int
    {
        if ($this->positionStart === null || $this->positionEnd === null) {
            return null;
        }

        return $this->positionStart - $this->positionEnd;
    }

    public function enteredTop10(): bool
    {
        return ($this->positionStart === null || $this->positionStart > 10)
            && $this->positionEnd !== null
            && $this->positionEnd <= 10;
    }
}
