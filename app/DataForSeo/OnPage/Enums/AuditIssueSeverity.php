<?php

declare(strict_types=1);

namespace App\DataForSeo\OnPage\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Sección 4 del SPEC: "severity (critical/warning/notice)".
 */
enum AuditIssueSeverity: string implements HasColor, HasLabel
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Notice = 'notice';

    public function getLabel(): string
    {
        return match ($this) {
            self::Critical => 'Crítico',
            self::Warning => 'Advertencia',
            self::Notice => 'Aviso',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Warning => 'warning',
            self::Notice => 'gray',
        };
    }
}
