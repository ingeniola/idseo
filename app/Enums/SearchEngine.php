<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SearchEngine: string implements HasLabel
{
    case Google = 'google';
    case Bing = 'bing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Bing => 'Bing',
        };
    }
}
