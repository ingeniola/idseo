<?php

declare(strict_types=1);

namespace App\GoogleSearchConsole;

use RuntimeException;

/**
 * Cualquier fallo al hablar con Google (OAuth o la API de Search
 * Console): token inválido/revocado, credenciales mal configuradas,
 * error de red, etc. A diferencia de las excepciones de DataForSEO
 * (Fase 1), no hay distinción transitorio/permanente ni reintento
 * automático: Search Console es de uso interno y bajo volumen (un
 * sync diario por proyecto conectado), no vale la pena esa
 * complejidad todavía.
 */
class GoogleSearchConsoleException extends RuntimeException {}
