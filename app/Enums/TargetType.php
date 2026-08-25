<?php

declare(strict_types=1);

namespace App\Enums;

enum TargetType: string
{
    case Domain = 'domain';
    case Subdomain = 'subdomain';
    case Url = 'url';

    public function label(): string
    {
        return match ($this) {
            self::Domain => 'Dominio completo',
            self::Subdomain => 'Subdominio',
            self::Url => 'URL específica',
        };
    }
}
