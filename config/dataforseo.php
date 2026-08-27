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

    /*
    |--------------------------------------------------------------------------
    | Estimación de costo — perfil de backlinks (Live)
    |--------------------------------------------------------------------------
    |
    | Fase 3, sección 5.1: "Backlinks". SyncBacklinkProfile hace 3
    | llamadas Live (summary, referring_domains, backlinks) por
    | actualización — esta es la suma estimada de las 3, no el precio
    | de una sola. Mismo caso que las estimaciones de arriba: sin
    | precio confirmado en la documentación pública disponible al
    | construir este módulo.
    |
    */

    'backlink_profile_live_cost_estimate' => env('DATAFORSEO_BACKLINK_PROFILE_LIVE_COST_ESTIMATE'),

    /*
    |--------------------------------------------------------------------------
    | Estimación de costo — comparativa de backlinks con competidor (Live)
    |--------------------------------------------------------------------------
    |
    | backlinks/summary/live, una sola llamada por comparación.
    |
    */

    'backlink_comparison_live_cost_estimate' => env('DATAFORSEO_BACKLINK_COMPARISON_LIVE_COST_ESTIMATE'),

    /*
    |--------------------------------------------------------------------------
    | Auditoría técnica on-page (Fase 3, sección 5 del SPEC)
    |--------------------------------------------------------------------------
    |
    | on_page/task_post es modo Standard, no Live, pero SÍ es una acción
    | paga disparada a demanda (no hay mandato del SPEC de auditar
    | periódicamente), así que igual necesita estimación de costo en la
    | UI (sección 3.5). Se cobra por página crawleada, no por llamada
    | fija — a diferencia de las estimaciones "por llamada" de arriba,
    | esta es "por página" y la UI debe mostrarlo como tal. Sin precio
    | confirmado en la documentación pública disponible al construir
    | este módulo — llenar con el precio real de la cuenta antes de
    | producción.
    |
    */

    'onpage_audit_cost_per_page_estimate' => env('DATAFORSEO_ONPAGE_AUDIT_COST_PER_PAGE_ESTIMATE'),

    'onpage_audit_default_max_crawl_pages' => (int) env('DATAFORSEO_ONPAGE_AUDIT_DEFAULT_MAX_CRAWL_PAGES', 100),

    /*
    |--------------------------------------------------------------------------
    | Límite de páginas al pedir on_page/pages
    |--------------------------------------------------------------------------
    |
    | ProcessOnPageAuditPostback pide los resultados por página en una
    | sola llamada con este límite (sección 3.3: verificar vía llamada
    | propia, no confiar en el postback). No se encontró el máximo real
    | que acepta el endpoint en la documentación pública disponible —
    | 1000 es un valor conservador, mayor que max_crawl_pages por
    | defecto, para no truncar auditorías normales.
    |
    */

    'onpage_pages_fetch_limit' => (int) env('DATAFORSEO_ONPAGE_PAGES_FETCH_LIMIT', 1000),

    /*
    |--------------------------------------------------------------------------
    | Reseñas de Google Business Profile (Fase 3, sección 5 del SPEC)
    |--------------------------------------------------------------------------
    |
    | business_data/google/reviews/task_post cobra por cada 10 reseñas
    | del `depth` solicitado (confirmado por búsqueda cruzada), no por
    | llamada fija — igual que la auditoría on-page, la estimación es
    | "por cada 10 reseñas". Sin precio confirmado en la documentación
    | pública disponible al construir este módulo — llenar con el
    | precio real de la cuenta antes de producción.
    |
    */

    'business_reviews_cost_per_10_estimate' => env('DATAFORSEO_BUSINESS_REVIEWS_COST_PER_10_ESTIMATE'),

    'business_reviews_default_depth' => (int) env('DATAFORSEO_BUSINESS_REVIEWS_DEFAULT_DEPTH', 20),

];
