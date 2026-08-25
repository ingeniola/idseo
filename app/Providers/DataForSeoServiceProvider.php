<?php

declare(strict_types=1);

namespace App\Providers;

use App\DataForSeo\CostControl\BudgetChecker;
use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\DataForSeoClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Los listeners de DataForSeoRequestCompleted (RecordDataForSeoCost,
 * RecordDataForSeoRequestLog) NO se registran aquí a mano: Laravel los
 * descubre automáticamente por convención (un método handle() tipado
 * al evento en app/Listeners). Registrarlos también manualmente los
 * duplicaba.
 */
class DataForSeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BudgetChecker::class, function () {
            return new BudgetChecker(
                monthlyBudgetUsd: (float) config('cost_control.monthly_budget_usd'),
            );
        });

        $this->app->singleton(CircuitBreaker::class);

        $this->app->singleton(DataForSeoClient::class, function (Application $app) {
            return new DataForSeoClient(
                login: (string) config('dataforseo.login'),
                password: (string) config('dataforseo.password'),
                baseUrl: (string) config('dataforseo.base_url'),
                timeout: (int) config('dataforseo.timeout'),
                rateLimitPerMinute: (int) config('dataforseo.rate_limit_per_minute'),
                maxTasksPerRequest: (int) config('dataforseo.max_tasks_per_request'),
                circuitBreaker: $app->make(CircuitBreaker::class),
            );
        });
    }
}
