<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SearchEngine;
use App\Enums\TargetType;
use App\Enums\TrackingFrequency;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => $this->faker->words(3, true),
            'domain' => $this->faker->unique()->domainName(),
            'target_type' => TargetType::Domain,
            'default_location_code' => 2340,
            'default_language_code' => 'es',
            'search_engine' => SearchEngine::Google,
            'tracking_frequency' => TrackingFrequency::Weekly,
            'is_active' => true,
        ];
    }
}
