<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook de postback
    |--------------------------------------------------------------------------
    |
    | Token secreto que DataForSEO debe mandar en la query string del
    | postback_url (sección 3.3 del SPEC). Sin esto configurado,
    | ScheduleRankTrackingTasks no debe armar un postback_url: mejor no
    | programar tareas que dejar el webhook abierto sin protección.
    |
    */

    'webhook_token' => env('DATAFORSEO_WEBHOOK_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Formato de resultado del postback
    |--------------------------------------------------------------------------
    |
    | 'advanced' es el que trae los bloques de SERP features (featured
    | snippet, local pack, People Also Ask, etc.) que pide la sección
    | 5.3 del SPEC — verificado contra la documentación pública antes
    | de usarlo.
    |
    */

    'postback_data' => 'advanced',

    /*
    |--------------------------------------------------------------------------
    | Reconciliación de tareas huérfanas
    |--------------------------------------------------------------------------
    |
    | Los postbacks se pueden perder (sección 3.3 del SPEC).
    | ReconcilePendingTasks recupera vía tasks_ready + task_get
    | cualquier tarea que lleve más de este número de horas en pending.
    |
    */

    'reconcile_pending_after_hours' => (int) env('DATAFORSEO_RECONCILE_PENDING_AFTER_HOURS', 6),

];
