<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Los 4 tipos de alerta que pide la Fase 2 (sección 5 del SPEC):
 * "caída de más de N posiciones, salida del top 10, entrada al top 3,
 * pérdida de featured snippet". Evaluados por DetectRankingAlerts
 * comparando las dos últimas Ranking de cada keyword.
 */
enum AlertType: string implements HasLabel
{
    case PositionDrop = 'position_drop';
    case ExitedTop10 = 'exited_top_10';
    case EnteredTop3 = 'entered_top_3';
    case LostFeaturedSnippet = 'lost_featured_snippet';

    public function getLabel(): string
    {
        return match ($this) {
            self::PositionDrop => 'Caída de posiciones',
            self::ExitedTop10 => 'Salió del top 10',
            self::EnteredTop3 => 'Entró al top 3',
            self::LostFeaturedSnippet => 'Perdió featured snippet',
        };
    }
}
