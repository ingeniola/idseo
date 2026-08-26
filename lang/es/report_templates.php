<?php

declare(strict_types=1);

return [
    'singular' => 'Plantilla de reporte',
    'plural' => 'Plantillas de reporte',
    'navigation_group' => 'Reportes',
    'global_template' => 'Plantilla global',

    'fields' => [
        'name' => 'Nombre',
        'client_id' => 'Cliente',
        'client_id_helper' => 'Vacío = plantilla global, disponible para cualquier cliente sin una plantilla propia.',
        'sections' => 'Secciones',
        'branding_overrides' => 'Branding (opcional, sobrescribe el del cliente)',
        'branding_logo_path' => 'Logo',
        'branding_logo_path_helper' => 'Si se deja vacío, se usa el logo del cliente.',
        'branding_primary_color' => 'Color primario',
        'reports_count' => 'Reportes generados',
        'created_at' => 'Creada',
    ],
];
