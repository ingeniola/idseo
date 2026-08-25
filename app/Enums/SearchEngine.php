<?php

declare(strict_types=1);

namespace App\Enums;

enum SearchEngine: string
{
    case Google = 'google';
    case Bing = 'bing';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Bing => 'Bing',
        };
    }
}
