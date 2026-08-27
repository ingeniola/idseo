<?php

declare(strict_types=1);

return [
    'singular' => 'Search Console',
    'plural' => 'Search Console',

    'fields' => [
        'date' => 'Fecha',
        'query' => 'Búsqueda',
        'clicks' => 'Clics',
        'impressions' => 'Impresiones',
        'ctr' => 'CTR',
        'position' => 'Posición promedio',
    ],

    'connect' => [
        'action' => 'Conectar Search Console',
    ],

    'disconnect' => [
        'action' => 'Desconectar (:site)',
        'success' => 'Search Console desconectado.',
    ],

    'empty' => [
        'heading' => 'Sin datos de Search Console todavía',
        'description_connected' => 'La sincronización corre una vez al día. Si acaba de conectar, los primeros datos aparecen en la próxima corrida.',
        'description_disconnected' => 'Conecte una cuenta de Google con acceso a este sitio para ver clics, impresiones y posición promedio.',
    ],
];
