<?php

declare(strict_types=1);

namespace App\DataForSeo\CostControl;

use App\DataForSeo\Events\CostBudgetExceeded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Corta la creación de tareas nuevas cuando el gasto del mes supera el
 * presupuesto configurado. El estado se guarda en cache sin TTL: solo
 * se limpia con una acción humana explícita (`php artisan
 * dataforseo:resume`), nunca automáticamente.
 */
class CircuitBreaker
{
    private const CACHE_KEY = 'dataforseo:circuit_breaker:tripped_reason';

    public function __construct(
        private readonly BudgetChecker $budgetChecker,
    ) {}

    public function isTripped(): bool
    {
        return Cache::has(self::CACHE_KEY);
    }

    public function reason(): ?string
    {
        return Cache::get(self::CACHE_KEY);
    }

    /**
     * Verifica el presupuesto global y activa el circuit breaker si se
     * superó. Idempotente: si ya estaba activo, no vuelve a disparar el
     * evento.
     */
    public function checkGlobalBudget(): bool
    {
        if ($this->isTripped()) {
            return true;
        }

        if (! $this->budgetChecker->isOverGlobalBudget()) {
            return false;
        }

        $monthSpend = $this->budgetChecker->monthSpend();
        $budget = $this->budgetChecker->globalBudget();

        $this->trip(sprintf(
            'Presupuesto global mensual superado: $%.2f gastados de un límite de $%.2f.',
            $monthSpend,
            $budget,
        ));

        CostBudgetExceeded::dispatch('global_budget', $monthSpend, $budget);

        return true;
    }

    public function trip(string $reason): void
    {
        Cache::forever(self::CACHE_KEY, $reason);

        Log::critical('dataforseo.circuit_breaker.tripped', ['reason' => $reason]);
    }

    public function resume(): void
    {
        Cache::forget(self::CACHE_KEY);

        Log::warning('dataforseo.circuit_breaker.resumed');
    }
}
