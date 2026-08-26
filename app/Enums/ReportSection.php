<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Catálogo de secciones disponibles para un reporte de marca blanca
 * (sección 5.4 del SPEC). `ReportTemplate::$sections` guarda un
 * arreglo de estos valores; el orden del arreglo es el orden en que
 * la sección aparece en el PDF.
 */
enum ReportSection: string implements HasLabel
{
    case ExecutiveSummary = 'executive_summary';
    case VisibilityEvolution = 'visibility_evolution';
    case PositionsTable = 'positions_table';
    case TopGains = 'top_gains';
    case TopLosses = 'top_losses';
    case NewKeywordsInTop10 = 'new_keywords_in_top_10';

    public function getLabel(): string
    {
        return match ($this) {
            self::ExecutiveSummary => 'Resumen ejecutivo',
            self::VisibilityEvolution => 'Evolución de visibilidad',
            self::PositionsTable => 'Tabla de posiciones',
            self::TopGains => 'Top ganancias',
            self::TopLosses => 'Top pérdidas',
            self::NewKeywordsInTop10 => 'Keywords nuevas en top 10',
        };
    }
}
