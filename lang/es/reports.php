<?php

declare(strict_types=1);

return [
    'singular' => 'Reporte',
    'plural' => 'Reportes',
    'not_sent' => 'No enviado',

    'fields' => [
        'period_start' => 'Desde',
        'period_end' => 'Hasta',
        'template_id' => 'Plantilla',
        'status' => 'Estado',
        'generated_by' => 'Generado por',
        'sent_at' => 'Enviado',
    ],

    'generate' => [
        'action' => 'Generar reporte',
        'dispatched' => 'Generación en proceso. El reporte aparecerá como completado en unos momentos.',
    ],

    'send' => [
        'dispatched' => 'Envío en proceso.',
    ],

    'actions' => [
        'download' => 'Descargar',
        'send' => 'Enviar por correo',
        'retry' => 'Reintentar',
    ],
];
