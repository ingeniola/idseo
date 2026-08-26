<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Umbral de caída de posiciones
    |--------------------------------------------------------------------------
    |
    | Fase 2, sección 5 del SPEC: "caída de más de N posiciones". Global
    | por ahora, sin override por proyecto — no existe una columna para
    | eso en `projects` y el SPEC no la pide explícitamente. Mismo
    | patrón que 'monthly_budget_usd' de cost_control.php: un umbral
    | global hasta que haya una razón concreta para uno por proyecto.
    |
    */

    'position_drop_threshold' => (int) env('ALERTS_POSITION_DROP_THRESHOLD', 5),

];
