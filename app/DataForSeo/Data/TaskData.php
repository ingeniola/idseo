<?php

declare(strict_types=1);

namespace App\DataForSeo\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class TaskData extends Data
{
    /**
     * @param  array<int, string>|null  $path
     * @param  array<string, mixed>|null  $data
     * @param  array<int, array<string, mixed>>|null  $result
     */
    public function __construct(
        public readonly string $id,
        public readonly int $statusCode,
        public readonly string $statusMessage,
        public readonly string $time,
        public readonly float $cost,
        public readonly int $resultCount,
        public readonly ?array $path,
        public readonly ?array $data,
        public readonly ?array $result,
    ) {}
}
