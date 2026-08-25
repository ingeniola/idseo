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

];
