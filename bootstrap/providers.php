<?php

use App\Providers\AppServiceProvider;
use App\Providers\DataForSeoServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\GoogleSearchConsoleServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\ReportServiceProvider;

return [
    AppServiceProvider::class,
    DataForSeoServiceProvider::class,
    GoogleSearchConsoleServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
    ReportServiceProvider::class,
];
