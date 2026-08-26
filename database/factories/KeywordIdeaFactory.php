<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SearchIntent;
use App\Models\KeywordIdea;
use App\Models\KeywordResearchSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeywordIdea>
 */
class KeywordIdeaFactory extends Factory
{
    protected $model = KeywordIdea::class;

    public function definition(): array
    {
        return [
            'session_id' => KeywordResearchSession::factory(),
            'keyword' => $this->faker->words(3, true),
            'search_volume' => $this->faker->numberBetween(0, 10000),
            'cpc' => $this->faker->randomFloat(2, 0, 10),
            'competition' => $this->faker->randomFloat(2, 0, 1),
            'difficulty' => $this->faker->numberBetween(0, 100),
            'intent' => $this->faker->randomElement(SearchIntent::cases())->value,
            'is_selected' => false,
        ];
    }
}
