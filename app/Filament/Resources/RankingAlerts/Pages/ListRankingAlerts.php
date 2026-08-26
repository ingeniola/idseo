<?php

declare(strict_types=1);

namespace App\Filament\Resources\RankingAlerts\Pages;

use App\Filament\Resources\RankingAlerts\RankingAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListRankingAlerts extends ListRecords
{
    protected static string $resource = RankingAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
