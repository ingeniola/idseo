<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TargetType: string implements HasLabel
{
    case Domain = 'domain';
    case Subdomain = 'subdomain';
    case Url = 'url';

    public function getLabel(): string
    {
        return match ($this) {
            self::Domain => 'Dominio completo',
            self::Subdomain => 'Subdominio',
            self::Url => 'URL específica',
        };
    }
}
