<?php

declare(strict_types=1);

namespace App\Filament\Resources\RankingAlerts;

use App\Filament\Resources\RankingAlerts\Pages\ListRankingAlerts;
use App\Filament\Resources\RankingAlerts\Tables\RankingAlertsTable;
use App\Models\RankingAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Fase 2, "Alertas" (sección 5 del SPEC). Solo lectura: las genera
 * DetectRankingAlerts, nadie las captura ni edita a mano — sin
 * formulario, sin acciones de crear/editar/borrar. A diferencia de
 * AuditLogResource, no está restringido a Admin: cualquier usuario
 * interno con acceso al panel puede verlas (son datos operativos de
 * proyecto, no de seguridad).
 */
class RankingAlertResource extends Resource
{
    protected static ?string $model = RankingAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('ranking_alerts.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('ranking_alerts.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ranking_alerts.plural');
    }

    public static function table(Table $table): Table
    {
        return RankingAlertsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRankingAlerts::route('/'),
        ];
    }
}
