<?php

declare(strict_types=1);

return [
    'singular' => 'Idea de keyword',
    'plural' => 'Investigación de keywords',

    'fields' => [
        'keyword' => 'Keyword',
        'seed_keyword' => 'Keyword semilla',
        'search_volume' => 'Volumen',
        'cpc' => 'CPC',
        'competition' => 'Competencia',
        'difficulty' => 'Dificultad',
        'intent' => 'Intención',
        'is_selected' => 'Promovida',
    ],

    'search' => [
        'action' => 'Buscar ideas',
        'modal_heading' => 'Buscar ideas de keywords',
        'modal_description_with_estimate' => 'Se va a consultar DataForSEO Labs a partir de una keyword semilla, contra la API en vivo. Costo estimado: :cost por búsqueda. El costo real queda registrado en el ledger de costos.',
        'modal_description_without_estimate' => 'Se va a consultar DataForSEO Labs a partir de una keyword semilla, contra la API en vivo. No hay un costo estimado configurado (DATAFORSEO_KEYWORD_IDEAS_LIVE_COST_ESTIMATE); el costo real queda registrado en el ledger de costos después de la llamada.',
        'submit' => 'Buscar',
        'budget_exceeded' => 'No se pudo buscar: el circuit breaker de presupuesto está activo.',
        'success' => 'Se encontraron :count idea(s) de keywords.',
        'rate_limited' => 'Demasiados intentos seguidos. Espera :seconds segundos antes de volver a intentarlo.',
    ],

    'promote' => [
        'action' => 'Promover a keywords',
        'modal_description' => 'Las ideas seleccionadas se agregan como keywords rastreadas del proyecto (ubicación e idioma por defecto del proyecto). Las que ya existan se omiten sin duplicar.',
        'success' => ':promoted de :requested idea(s) agregadas como keywords rastreadas (el resto ya existía).',
    ],
];
