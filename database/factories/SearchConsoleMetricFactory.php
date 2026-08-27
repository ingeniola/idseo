<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\SearchConsoleMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchConsoleMetric>
 */
class SearchConsoleMetricFactory extends Factory
{
    protected $model = SearchConsoleMetric::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'date' => now()->toDateString(),
            'query' => $this->faker->words(3, true),
            'clicks' => $this->faker->numberBetween(0, 500),
            'impressions' => $this->faker->numberBetween(0, 5000),
            'ctr' => $this->faker->randomFloat(4, 0, 1),
            'position' => $this->faker->randomFloat(2, 1, 100),
        ];
    }
}
