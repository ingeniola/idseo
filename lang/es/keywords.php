<?php

declare(strict_types=1);

return [
    'singular' => 'Keyword',
    'plural' => 'Keywords',

    'fields' => [
        'keyword' => 'Keyword',
        'location_code' => 'Ubicación',
        'language_code' => 'Idioma',
        'tags' => 'Etiquetas',
        'search_volume' => 'Volumen',
        'cpc' => 'CPC',
        'competition' => 'Competencia',
        'volume_updated_at' => 'Volumen actualizado',
        'is_active' => 'Activa',
    ],

    'bulk_paste' => [
        'action' => 'Pegado masivo',
        'modal_heading' => 'Agregar keywords por pegado masivo',
        'keywords_raw' => 'Keywords (una por línea)',
        'keywords_raw_helper' => 'Se ignoran líneas vacías. Las que ya existan con la misma ubicación e idioma en este proyecto se omiten sin duplicar.',
        'submit' => 'Agregar',
    ],

    'enrich' => [
        'action' => 'Enriquecer volumen',
        'modal_heading' => 'Enriquecer volumen de búsqueda',
        'modal_description_with_estimate' => 'Se va a consultar el volumen de búsqueda para :count keyword(s) seleccionadas contra la API en vivo de DataForSEO. Costo estimado: :cost por llamada (independiente del número de keywords, hasta 1000 por llamada). El costo real queda registrado en el ledger de costos.',
        'modal_description_without_estimate' => 'Se va a consultar el volumen de búsqueda para :count keyword(s) seleccionadas contra la API en vivo de DataForSEO. No hay un costo estimado configurado (DATAFORSEO_SEARCH_VOLUME_LIVE_COST_ESTIMATE); el costo real queda registrado en el ledger de costos después de la llamada.',
        'submit' => 'Enriquecer',
        'budget_exceeded' => 'No se pudo enriquecer: el circuit breaker de presupuesto está activo.',
        'success' => ':updated de :requested keyword(s) actualizadas.',
    ],
];
