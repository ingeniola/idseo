<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs;

use App\Enums\UserRole;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Solo lectura: nunca hay create/edit/delete de una fila de auditoría
 * (sección 9 y Fase 1 paso 12 del SPEC). Restringido a rol Admin —
 * canAccess()/canViewAny() se sobrescriben directamente en vez de
 * usar una Policy, porque no hay ninguna otra acción de Filament
 * sobre AuditLog que necesite autorización granular por registro.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('audit_logs.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('audit_logs.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('audit_logs.plural');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->role === UserRole::Admin;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
