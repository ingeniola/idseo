<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Analyst = 'analyst';
    case Client = 'client';

    public function label(): string
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
