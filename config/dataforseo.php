<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales
    |--------------------------------------------------------------------------
    |
    | Autenticación HTTP Basic contra la API de DataForSEO. Nunca se
    | almacenan en el repositorio ni en base de datos sin encriptar.
    |
    */

    'login' => env('DATAFORSEO_LOGIN'),

    'password' => env('DATAFORSEO_PASSWORD'),

    'base_url' => env('DATAFORSEO_BASE_URL', 'https://api.dataforseo.com/v3/'),

    'timeout' => (int) env('DATAFORSEO_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Límite propio, por debajo del límite oficial de la API (2000 req/min
    | documentado por DataForSEO), para dejar margen de seguridad.
    |
    */

    'rate_limit_per_minute' => (int) env('DATAFORSEO_RATE_LIMIT_PER_MINUTE', 1500),

    /*
    |--------------------------------------------------------------------------
    | Máximo de tareas por request
    |--------------------------------------------------------------------------
    |
    | El endpoint de task_post acepta un arreglo de tareas por request.
    | DataForSeoClient rechaza lotes mayores a este número; agrupar en
    | varios requests es responsabilidad de quien orquesta el lote (Jobs).
    |
    */

    'max_tasks_per_request' => (int) env('DATAFORSEO_MAX_TASKS_PER_REQUEST', 100),

    /*
    |--------------------------------------------------------------------------
    | Estimación de costo — enriquecimiento de volumen (Live)
    |--------------------------------------------------------------------------
    |
    | keywords_data/google_ads/search_volume/live cobra por llamada, sin
    | importar el número de keywords (hasta 1000 por llamada). No se
    | encontró un precio confirmado en la documentación pública
    | disponible al construir este módulo — llenar este valor con el
    | precio real de la cuenta antes de usarlo en producción. Si queda
    | null, la UI avisa que no hay estimación configurada en vez de
    | inventar una cifra (sección 3.5 del SPEC: "la UI muestra estimación
    | de costo y pide confirmación").
    |
    */

    'search_volume_live_cost_estimate' => env('DATAFORSEO_SEARCH_VOLUME_LIVE_COST_ESTIMATE'),

    /*
    |--------------------------------------------------------------------------
    | Estimación de costo — investigación de keywords (Labs, Live)
    |--------------------------------------------------------------------------
    |
    | dataforseo_labs/google/keyword_ideas/live (Fase 2, sección 5.2 del
    | SPEC: "Investigación de keywords"). Mismo caso que la estimación
    | de arriba: no se encontró un precio confirmado en la
    | documentación pública disponible al construir este módulo — llenar
    | con el precio real de la cuenta antes de usarlo en producción.
    |
    */

    'keyword_ideas_live_cost_estimate' => env('DATAFORSEO_KEYWORD_IDEAS_LIVE_COST_ESTIMATE'),

];
