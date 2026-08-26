<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectVisibilitySnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectVisibilitySnapshot>
 */
class ProjectVisibilitySnapshotFactory extends Factory
{
    protected $model = ProjectVisibilitySnapshot::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'calculated_at' => now()->toDateString(),
            'visibility_score' => $this->faker->randomFloat(2, 0, 100),
            'tracked_keywords_count' => $this->faker->numberBetween(1, 100),
            'keywords_in_top_3' => $this->faker->numberBetween(0, 10),
            'keywords_in_top_10' => $this->faker->numberBetween(0, 30),
            'keywords_in_top_20' => $this->faker->numberBetween(0, 50),
            'average_position' => $this->faker->randomFloat(2, 1, 100),
        ];
    }
}
