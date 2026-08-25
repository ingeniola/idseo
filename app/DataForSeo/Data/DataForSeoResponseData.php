<?php

declare(strict_types=1);

namespace App\DataForSeo\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Sobre (envelope) genérico de cualquier respuesta de la API v3 de
 * DataForSEO. La forma de `TaskData::$result` es específica de cada
 * endpoint y se tipa en la capa de "endpoints por servicio" de fases
 * posteriores; aquí solo se modela lo que es común a toda la API.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class DataForSeoResponseData extends Data
{
    private const SUCCESS_RANGE_START = 20000;

    private const SUCCESS_RANGE_END = 20999;

    private const TRANSIENT_RANGE_START = 50000;

    private const TRANSIENT_RANGE_END = 59999;

    /**
     * @param  array<int, TaskData>  $tasks
     */
    public function __construct(
        public readonly string $version,
        public readonly int $statusCode,
        public readonly string $statusMessage,
        public readonly string $time,
        public readonly float $cost,
        public readonly int $tasksCount,
        public readonly int $tasksError,
        #[DataCollectionOf(TaskData::class)]
        public readonly array $tasks,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->statusCode >= self::SUCCESS_RANGE_START
            && $this->statusCode <= self::SUCCESS_RANGE_END;
    }

    public function isTransientError(): bool
    {
        return $this->statusCode >= self::TRANSIENT_RANGE_START
            && $this->statusCode <= self::TRANSIENT_RANGE_END;
    }
}
