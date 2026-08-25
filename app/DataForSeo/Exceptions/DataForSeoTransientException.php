<?php

declare(strict_types=1);

namespace App\DataForSeo\Exceptions;

/**
 * Fallo pasajero: timeout, HTTP 429, HTTP 5xx, o status_code de DataForSEO
 * en el rango 50000-59999. Se puede reintentar con backoff.
 */
final class DataForSeoTransientException extends DataForSeoException {}
