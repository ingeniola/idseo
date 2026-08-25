<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Presupuesto global mensual
    |--------------------------------------------------------------------------
    |
    | Umbral en USD contra el que se compara el gasto acumulado del mes
    | (suma de cost_ledger). 0 = sin límite configurado (el circuit
    | breaker global nunca corta por presupuesto, solo queda el chequeo
    | de saldo de cuenta).
    |
    | Los presupuestos por cliente y por proyecto viven en sus propias
    | tablas (clients.monthly_budget_cap, etc.) y se implementan cuando
    | esas tablas existan (Fase 1, pasos 4 y 6 del SPEC).
    |
    */

    'monthly_budget_usd' => (float) env('DATAFORSEO_MONTHLY_BUDGET_USD', 0),

    /*
    |--------------------------------------------------------------------------
    | Alerta de saldo bajo
    |--------------------------------------------------------------------------
    |
    | Umbral en USD para alertar cuando el saldo de la cuenta DataForSEO
    | (consultado vía SyncAccountBalance, fase posterior) esté bajo.
    |
    */

    'low_balance_alert_threshold_usd' => (float) env('DATAFORSEO_LOW_BALANCE_ALERT_USD', 50),

];
