<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Analyst = 'analyst';
    case Client = 'client';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Manager => 'Gerente',
            self::Analyst => 'Analista',
            self::Client => 'Cliente',
        };
    }

    public function isInternal(): bool
    {
        return $this !== self::Client;
    }
}
