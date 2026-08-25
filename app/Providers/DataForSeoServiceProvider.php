<?php

declare(strict_types=1);

namespace App\Providers;

use App\DataForSeo\DataForSeoClient;
use Illuminate\Support\ServiceProvider;

class DataForSeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DataForSeoClient::class, function () {
            return new DataForSeoClient(
                login: (string) config('dataforseo.login'),
                password: (string) config('dataforseo.password'),
                baseUrl: (string) config('dataforseo.base_url'),
                timeout: (int) config('dataforseo.timeout'),
                rateLimitPerMinute: (int) config('dataforseo.rate_limit_per_minute'),
                maxTasksPerRequest: (int) config('dataforseo.max_tasks_per_request'),
            );
        });
    }
}
