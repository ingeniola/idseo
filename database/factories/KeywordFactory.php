<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Keyword>
 */
class KeywordFactory extends Factory
{
    protected $model = Keyword::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'keyword' => $this->faker->unique()->words(3, true),
            'location_code' => 2340,
            'language_code' => 'es',
            'tags' => [],
            'search_volume' => $this->faker->numberBetween(0, 10_000),
            'cpc' => $this->faker->randomFloat(2, 0, 10),
            'competition' => $this->faker->randomFloat(2, 0, 1),
            'volume_updated_at' => now(),
            'is_active' => true,
        ];
    }
}
