<?php

declare(strict_types=1);

namespace App\DataForSeo\Exceptions;

/**
 * Fallo permanente: parámetros inválidos, ubicación inexistente, o
 * status_code de DataForSEO en el rango 40000-49999. No se reintenta.
 */
final class DataForSeoPermanentException extends DataForSeoException {}
