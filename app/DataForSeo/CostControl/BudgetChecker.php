<?php

declare(strict_types=1);

namespace App\DataForSeo\CostControl;

use App\Models\CostLedger;
use Illuminate\Support\Carbon;

/**
 * Cálculos de gasto sobre `cost_ledger`. Solo cubre el presupuesto
 * global por ahora: los presupuestos por cliente y por proyecto
 * requieren las tablas `clients`/`projects` (Fase 1, pasos 4 y 6).
 */
class BudgetChecker
{
    public function __construct(
        private readonly float $monthlyBudgetUsd,
    ) {}

    public function monthSpend(?Carbon $reference = null): float
    {
        $reference ??= Carbon::now();

        return (float) CostLedger::query()
            ->whereBetween('date', [
                $reference->copy()->startOfMonth()->toDateString(),
                $reference->copy()->endOfMonth()->toDateString(),
            ])
            ->sum('cost');
    }

    /**
     * Proyección lineal simple: gasto acumulado / días transcurridos *
     * días del mes. No es una predicción sofisticada, solo una señal
     * de alerta temprana para el dashboard.
     */
    public function projectedMonthEnd(?Carbon $reference = null): float
    {
        $reference ??= Carbon::now();

        $daysElapsed = max($reference->day, 1);
        $daysInMonth = $reference->daysInMonth;

        return $this->monthSpend($reference) / $daysElapsed * $daysInMonth;
    }

    public function hasGlobalBudget(): bool
    {
        return $this->monthlyBudgetUsd > 0;
    }

    public function isOverGlobalBudget(?Carbon $reference = null): bool
    {
        return $this->hasGlobalBudget() && $this->monthSpend($reference) >= $this->monthlyBudgetUsd;
    }

    public function globalBudget(): float
    {
        return $this->monthlyBudgetUsd;
    }
}
