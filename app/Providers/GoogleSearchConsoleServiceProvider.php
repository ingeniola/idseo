<?php

declare(strict_types=1);

namespace App\Providers;

use App\GoogleSearchConsole\GoogleSearchConsoleClient;
use Illuminate\Support\ServiceProvider;

class GoogleSearchConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleSearchConsoleClient::class, function () {
            return new GoogleSearchConsoleClient(
                clientId: (string) config('services.google_search_console.client_id'),
                clientSecret: (string) config('services.google_search_console.client_secret'),
                redirectUri: (string) config('services.google_search_console.redirect'),
            );
        });
    }
}
