<?php

declare(strict_types=1);

return [
    'title' => 'Menciones en LLMs (GEO)',

    'fields' => [
        'platform' => 'Plataforma',
        'question' => 'Pregunta',
        'answer' => 'Respuesta',
        'captured_at' => 'Capturada',
        'sources' => 'Fuentes citadas',
    ],

    'platforms' => [
        'chat_gpt' => 'ChatGPT',
        'google' => 'Google AI Overview',
    ],

    'search' => [
        'action' => 'Buscar menciones',
        'modal_heading' => 'Buscar menciones en LLMs',
        'modal_description_with_estimate' => 'Se va a consultar en vivo cómo se menciona el dominio de este proyecto en respuestas de IA. Costo estimado: :cost. El costo real queda registrado en el ledger de costos.',
        'modal_description_without_estimate' => 'Se va a consultar en vivo cómo se menciona el dominio de este proyecto en respuestas de IA. No hay un costo estimado configurado (DATAFORSEO_LLM_MENTIONS_SEARCH_LIVE_COST_ESTIMATE); el costo real queda registrado en el ledger de costos después de la llamada.',
        'platform' => 'Plataforma',
        'submit' => 'Buscar',
        'budget_exceeded' => 'No se pudo buscar: el circuit breaker de presupuesto está activo.',
        'success' => 'Se encontraron :count mención(es).',
        'rate_limited' => 'Demasiados intentos seguidos. Espera :seconds segundos antes de volver a intentarlo.',
    ],

    'view' => [
        'action' => 'Ver',
        'modal_heading' => 'Mención en LLM',
        'no_sources' => 'Sin fuentes citadas.',
        'close' => 'Cerrar',
    ],
];
