<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Los 4 tipos de search intent que documenta DataForSEO
 * (search_intent_info.main_intent): informational, navigational,
 * commercial, transactional. Se usa solo para mostrar una etiqueta en
 * español en la UI (KeywordIdea::intent se guarda como string plano,
 * no casteado a este enum) — la presencia y el valor exacto de
 * search_intent_info en la respuesta de keyword_ideas/live no está
 * 100% confirmada contra la documentación pública disponible al
 * construir este módulo (sí está confirmada para keyword_overview y
 * historical_keyword_data). Si DataForSEO devuelve un valor que no
 * está en este enum, tryFrom() devuelve null y la UI muestra el string
 * crudo en vez de fallar.
 */
enum SearchIntent: string implements HasLabel
{
    case Informational = 'informational';
    case Navigational = 'navigational';
    case Commercial = 'commercial';
    case Transactional = 'transactional';

    public function getLabel(): string
    {
        return match ($this) {
            self::Informational => 'Informacional',
            self::Navigational => 'Navegacional',
            self::Commercial => 'Comercial',
            self::Transactional => 'Transaccional',
        };
    }
}
