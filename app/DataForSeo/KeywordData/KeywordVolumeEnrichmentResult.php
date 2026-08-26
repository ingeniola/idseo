<?php

declare(strict_types=1);

namespace App\DataForSeo\KeywordData;

final class KeywordVolumeEnrichmentResult
{
    public function __construct(
        public readonly int $requested,
        public readonly int $updated,
    ) {}
}
