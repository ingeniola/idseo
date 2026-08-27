<?php

declare(strict_types=1);

return [
    'title' => 'Auditoría técnica on-page',

    'fields' => [
        'status' => 'Estado',
        'started_at' => 'Iniciada',
        'completed_at' => 'Completada',
        'pages_crawled' => 'Páginas crawleadas',
        'onpage_score' => 'Score on-page',
        'cost' => 'Costo',
        'url' => 'URL',
        'severity' => 'Severidad',
        'issue_type' => 'Tipo',
        'message' => 'Mensaje',
    ],

    'trigger' => [
        'action' => 'Auditar sitio',
        'modal_heading' => 'Auditar el sitio',
        'modal_description_with_estimate' => 'Se va a crawlear el dominio de este proyecto (hasta :max_pages páginas) con la API on_page de DataForSEO (modo Standard, en segundo plano). Costo estimado: :cost por página. El costo real queda registrado en el ledger de costos.',
        'modal_description_without_estimate' => 'Se va a crawlear el dominio de este proyecto (hasta :max_pages páginas) con la API on_page de DataForSEO (modo Standard, en segundo plano). No hay un costo estimado configurado (DATAFORSEO_ONPAGE_AUDIT_COST_PER_PAGE_ESTIMATE); DataForSEO cobra por página crawleada, el costo real queda registrado en el ledger de costos cuando termine.',
        'max_crawl_pages' => 'Máximo de páginas a crawlear',
        'submit' => 'Auditar',
        'budget_exceeded' => 'No se pudo iniciar la auditoría: el circuit breaker de presupuesto está activo.',
        'success' => 'Auditoría iniciada. Los resultados aparecerán en esta tabla cuando termine el crawl (puede tardar minutos u horas).',
        'rate_limited' => 'Demasiados intentos seguidos. Espera :seconds segundos antes de volver a intentarlo.',
    ],

    'issues' => [
        'action' => 'Ver issues',
        'modal_heading' => 'Issues de la auditoría',
        'empty' => 'Esta auditoría no encontró issues (o todavía no termina).',
        'close' => 'Cerrar',
    ],
];
