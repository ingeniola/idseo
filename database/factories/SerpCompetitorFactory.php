<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\SerpCompetitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerpCompetitor>
 */
class SerpCompetitorFactory extends Factory
{
    protected $model = SerpCompetitor::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'domain' => $this->faker->domainName(),
            'keywords_overlap' => $this->faker->numberBetween(1, 20),
            'avg_position' => $this->faker->randomFloat(2, 1, 100),
            'visibility_score' => $this->faker->randomFloat(2, 0, 100),
            'calculated_at' => now()->toDateString(),
        ];
    }
}
