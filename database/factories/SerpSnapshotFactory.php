<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Keyword;
use App\Models\SerpSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerpSnapshot>
 */
class SerpSnapshotFactory extends Factory
{
    protected $model = SerpSnapshot::class;

    public function definition(): array
    {
        return [
            'keyword_id' => Keyword::factory(),
            'captured_at' => now(),
            'raw_response' => [],
            'top_results' => [],
        ];
    }
}
