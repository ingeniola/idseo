<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Keyword;
use App\Models\Ranking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ranking>
 */
class RankingFactory extends Factory
{
    protected $model = Ranking::class;

    public function definition(): array
    {
        return [
            'keyword_id' => Keyword::factory(),
            'checked_at' => now()->toDateString(),
            'position' => $this->faker->numberBetween(1, 100),
            'previous_position' => $this->faker->numberBetween(1, 100),
            'url' => $this->faker->url(),
            'serp_features' => [],
            'estimated_traffic' => $this->faker->numberBetween(0, 5_000),
            'is_featured_snippet' => false,
            'is_local_pack' => false,
        ];
    }
}
