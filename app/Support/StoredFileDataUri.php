<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Convierte un archivo guardado en el disco por defecto de Filament
 * (config('filament.default_filesystem_disk'), normalmente 'local' —
 * privado, ver .env FILESYSTEM_DISK) a un data URI embebible.
 *
 * Se usa en dos lugares que no pueden simplemente enlazar al archivo
 * por URL: el PDF de reportes (ReportDataBuilder), que Browsershot
 * renderiza con Chromium en un proceso aparte que no ve la misma ruta
 * local; y el layout del portal de cliente, porque el logo vive en un
 * disco privado y exponerlo por una URL pública solo para esto sería
 * más superficie de la necesaria.
 */
class StoredFileDataUri
{
    public static function from(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk(config('filament.default_filesystem_disk', 'local'));

        if (! $disk->exists($path)) {
            return null;
        }

        $mimeType = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($path));
    }
}
