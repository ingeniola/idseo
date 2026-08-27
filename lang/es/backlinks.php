<?php

declare(strict_types=1);

return [
    'referring_domains_title' => 'Dominios referentes',

    'fields' => [
        'source_domain' => 'Dominio de origen',
        'target_url' => 'URL de destino',
        'anchor' => 'Anchor',
        'dofollow' => 'Dofollow',
        'domain_rank' => 'Rank de dominio',
        'first_seen' => 'Visto desde',
        'last_seen' => 'Visto hasta',
        'is_lost' => 'Perdido',
        'domain' => 'Dominio',
        'backlinks_count' => 'Backlinks',
        'competitor_domain' => 'Dominio del competidor',
    ],

    'sync' => [
        'action' => 'Actualizar perfil de backlinks',
        'modal_heading' => 'Actualizar perfil de backlinks',
        'modal_description_with_estimate' => 'Se van a hacer 3 llamadas en vivo a DataForSEO (resumen, dominios referentes y backlinks) para el dominio de este proyecto. Costo estimado: :cost. El costo real queda registrado en el ledger de costos.',
        'modal_description_without_estimate' => 'Se van a hacer 3 llamadas en vivo a DataForSEO (resumen, dominios referentes y backlinks) para el dominio de este proyecto. No hay un costo estimado configurado (DATAFORSEO_BACKLINK_PROFILE_LIVE_COST_ESTIMATE); el costo real queda registrado en el ledger de costos después de la llamada.',
        'submit' => 'Actualizar',
        'budget_exceeded' => 'No se pudo actualizar: el circuit breaker de presupuesto está activo.',
        'success' => 'Perfil de backlinks actualizado.',
        'rate_limited' => 'Demasiados intentos seguidos. Espera :seconds segundos antes de volver a intentarlo.',
    ],

    'compare' => [
        'action' => 'Comparar con competidor',
        'modal_heading' => 'Comparar backlinks con un competidor',
        'modal_description_with_estimate' => 'Se va a consultar el resumen de backlinks del dominio indicado contra la API en vivo de DataForSEO. Costo estimado: :cost. El costo real queda registrado en el ledger de costos.',
        'modal_description_without_estimate' => 'Se va a consultar el resumen de backlinks del dominio indicado contra la API en vivo de DataForSEO. No hay un costo estimado configurado (DATAFORSEO_BACKLINK_COMPARISON_LIVE_COST_ESTIMATE); el costo real queda registrado en el ledger de costos después de la llamada.',
        'submit' => 'Comparar',
        'budget_exceeded' => 'No se pudo comparar: el circuit breaker de presupuesto está activo.',
        'success' => ':domain — :backlinks backlinks, :referring_domains dominios referentes, rank :rank.',
        'rate_limited' => 'Demasiados intentos seguidos. Espera :seconds segundos antes de volver a intentarlo.',
    ],
];
