<?php

use App\Providers\AppServiceProvider;
use App\Providers\DataForSeoServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    DataForSeoServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];
