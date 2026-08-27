<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LlmMention;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LlmMention>
 */
class LlmMentionFactory extends Factory
{
    protected $model = LlmMention::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'platform' => 'chat_gpt',
            'question' => $this->faker->sentence().'?',
            'answer' => $this->faker->paragraph(),
            'sources' => [['url' => $this->faker->url(), 'domain' => $this->faker->domainName(), 'title' => $this->faker->sentence()]],
            'captured_at' => now(),
            'raw' => null,
        ];
    }
}
