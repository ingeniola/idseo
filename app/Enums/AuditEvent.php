<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Catálogo de eventos de auditoría de autorización (sección 9 y Fase
 * 1 paso 12 del SPEC). Deliberadamente acotado a eventos relacionados
 * con quién entró, a quién se le negó acceso, y quién disparó una
 * acción sensible — no un log genérico de toda la aplicación.
 */
enum AuditEvent: string implements HasLabel
{
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case AuthorizationDenied = 'authorization_denied';
    case ReportDownloaded = 'report_downloaded';
    case PaidActionTriggered = 'paid_action_triggered';

    public function getLabel(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'Inicio de sesión exitoso',
            self::LoginFailed => 'Inicio de sesión fallido',
            self::Logout => 'Cierre de sesión',
            self::AuthorizationDenied => 'Acceso denegado',
            self::ReportDownloaded => 'Reporte descargado',
            self::PaidActionTriggered => 'Acción con costo disparada',
        };
    }
}
