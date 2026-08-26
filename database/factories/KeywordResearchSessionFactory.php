<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KeywordResearchSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeywordResearchSession>
 */
class KeywordResearchSessionFactory extends Factory
{
    protected $model = KeywordResearchSession::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'seed_keyword' => $this->faker->words(2, true),
            'source_endpoint' => 'dataforseo_labs/google/keyword_ideas/live',
            'cost' => $this->faker->randomFloat(6, 0, 1),
            'created_at' => now(),
        ];
    }
}
