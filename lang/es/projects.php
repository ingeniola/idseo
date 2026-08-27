<?php

declare(strict_types=1);

return [
    'singular' => 'Proyecto',
    'plural' => 'Proyectos',
    'navigation_group' => 'Clientes y proyectos',

    'fields' => [
        'client_id' => 'Cliente',
        'name' => 'Nombre',
        'domain' => 'Dominio',
        'google_business_place_id' => 'Place ID de Google Business',
        'google_business_place_id_help' => 'Identificador de la ficha en Google Maps (Place ID). Necesario para sincronizar reseñas de Google Business Profile.',
        'target_type' => 'Tipo de objetivo',
        'default_location_code' => 'Ubicación por defecto',
        'default_language_code' => 'Idioma por defecto',
        'search_engine' => 'Buscador',
        'tracking_frequency' => 'Frecuencia de seguimiento',
        'is_active' => 'Activo',
        'users' => 'Usuarios asignados',
        'keywords_count' => 'Keywords',
        'created_at' => 'Creado',
    ],

    'visibility_chart' => [
        'heading' => 'Visibilidad agregada del proyecto',
        'dataset_label' => 'Visibilidad',
    ],

    'edit_tab_label' => 'Configuración del proyecto',
];
