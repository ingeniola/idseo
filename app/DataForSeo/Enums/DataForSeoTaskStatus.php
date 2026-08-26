<?php

declare(strict_types=1);

namespace App\DataForSeo\Enums;

use Filament\Support\Contracts\HasLabel;

enum DataForSeoTaskStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Completed => 'Completada',
            self::Failed => 'Falló',
        };
    }
}
