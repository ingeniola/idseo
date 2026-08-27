<?php

declare(strict_types=1);

namespace App\DataForSeo\KeywordData;

final class KeywordVolumeEnrichmentResult
{
    /**
     * @param  array<int, string>  $failures  Mensajes de error únicos de
     *                                        las tareas de DataForSEO que
     *                                        no vinieron con status_code
     *                                        exitoso (ej. un language_code
     *                                        rechazado por este endpoint
     *                                        específico) — sin esto, un
     *                                        `updated` en 0 no se
     *                                        distingue de "no había nada
     *                                        que actualizar".
     */
    public function __construct(
        public readonly int $requested,
        public readonly int $updated,
        public readonly array $failures = [],
    ) {}
}
