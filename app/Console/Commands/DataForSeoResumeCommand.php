<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DataForSeo\CostControl\CircuitBreaker;
use Illuminate\Console\Command;

class DataForSeoResumeCommand extends Command
{
    protected $signature = 'dataforseo:resume';

    protected $description = 'Reanuda la creación de tareas en DataForSEO tras un corte del circuit breaker de presupuesto';

    public function handle(CircuitBreaker $circuitBreaker): int
    {
        if (! $circuitBreaker->isTripped()) {
            $this->info('El circuit breaker no está activo; no hay nada que reanudar.');

            return self::SUCCESS;
        }

        $this->warn('Motivo del corte: '.$circuitBreaker->reason());

        if (! $this->confirm('¿Confirmas que revisaste el gasto y quieres reanudar la creación de tareas?')) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $circuitBreaker->resume();

        $this->info('Circuit breaker reanudado. La creación de tareas queda habilitada de nuevo.');

        return self::SUCCESS;
    }
}
