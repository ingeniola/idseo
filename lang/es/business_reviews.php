<?php

declare(strict_types=1);

return [
    'title' => 'Reseñas de Google Business Profile',

    'fields' => [
        'reviewer_name' => 'Reseñador',
        'rating' => 'Calificación',
        'review_text' => 'Reseña',
        'published_at' => 'Publicada',
        'owner_answer' => 'Respondida',
        'is_local_guide' => 'Local Guide',
    ],

    'sync' => [
        'action' => 'Sincronizar reseñas',
        'modal_heading' => 'Sincronizar reseñas de Google Business',
        'modal_description_with_estimate' => 'Se van a traer hasta :depth reseñas del Place ID configurado en el proyecto (modo Standard, en segundo plano). Costo estimado: :cost por cada 10 reseñas. El costo real queda registrado en el ledger de costos.',
        'modal_description_without_estimate' => 'Se van a traer hasta :depth reseñas del Place ID configurado en el proyecto (modo Standard, en segundo plano). No hay un costo estimado configurado (DATAFORSEO_BUSINESS_REVIEWS_COST_PER_10_ESTIMATE); DataForSEO cobra por cada 10 reseñas, el costo real queda registrado en el ledger de costos cuando termine.',
        'depth' => 'Cantidad de reseñas a traer',
        'submit' => 'Sincronizar',
        'missing_place_id' => 'Este proyecto no tiene un Place ID de Google Business configurado. Configúralo en el formulario del proyecto antes de sincronizar.',
        'budget_exceeded' => 'No se pudo sincronizar: el circuit breaker de presupuesto está activo.',
        'success' => 'Sincronización iniciada. Las reseñas aparecerán en esta tabla cuando termine (puede tardar minutos).',
        'rate_limited' => 'Demasiados intentos seguidos. Espera :seconds segundos antes de volver a intentarlo.',
    ],
];
