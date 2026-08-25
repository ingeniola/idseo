<?php

declare(strict_types=1);

namespace App\DataForSeo\Exceptions;

/**
 * El circuit breaker de presupuesto está activo: no se crean tareas
 * nuevas hasta que una persona lo reanude explícitamente
 * (`php artisan dataforseo:resume`).
 */
final class DataForSeoBudgetExceededException extends DataForSeoException {}
