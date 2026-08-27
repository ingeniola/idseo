<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessReview;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessReview>
 */
class BusinessReviewFactory extends Factory
{
    protected $model = BusinessReview::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'review_id' => $this->faker->uuid(),
            'reviewer_name' => $this->faker->name(),
            'profile_image_url' => $this->faker->imageUrl(),
            'rating' => $this->faker->numberBetween(1, 5),
            'review_text' => $this->faker->paragraph(),
            'published_at' => now()->subDays($this->faker->numberBetween(0, 365)),
            'owner_answer' => null,
            'owner_answered_at' => null,
            'is_local_guide' => $this->faker->boolean(20),
            'raw' => null,
        ];
    }
}
