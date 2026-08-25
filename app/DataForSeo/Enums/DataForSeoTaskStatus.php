<?php

declare(strict_types=1);

namespace App\DataForSeo\Enums;

enum DataForSeoTaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Completed => 'Completada',
            self::Failed => 'Falló',
        };
    }
}
