<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\DataForSeo\CostControl\BudgetChecker;
use App\DataForSeo\CostControl\CircuitBreaker;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Cubre "gasto del mes" y "proyección a fin de mes" de la sección 3.5
 * del SPEC. "Top 10 proyectos por consumo" y "costo promedio por
 * reporte generado" quedan pendientes: necesitan `clients`/`projects`
 * (Fase 1, paso 4) y `reports` (Fase 1, paso 10) respectivamente.
 */
class CostOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $budgetChecker = app(BudgetChecker::class);
        $circuitBreaker = app(CircuitBreaker::class);

        $monthSpend = $budgetChecker->monthSpend();
        $projected = $budgetChecker->projectedMonthEnd();

        $budgetStat = Stat::make(
            'Presupuesto global',
            $budgetChecker->hasGlobalBudget()
                ? '$'.number_format($budgetChecker->globalBudget(), 2)
                : 'Sin configurar',
        );

        if ($circuitBreaker->isTripped()) {
            $budgetStat = $budgetStat
                ->description('Circuit breaker activo: '.$circuitBreaker->reason())
                ->color('danger');
        }

        return [
            Stat::make('Gasto del mes', '$'.number_format($monthSpend, 2)),
            Stat::make('Proyección a fin de mes', '$'.number_format($projected, 2)),
            $budgetStat,
        ];
    }
}
