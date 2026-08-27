<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\ReferringDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferringDomain>
 */
class ReferringDomainFactory extends Factory
{
    protected $model = ReferringDomain::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'domain' => $this->faker->domainName(),
            'backlinks_count' => $this->faker->numberBetween(1, 500),
            'first_seen' => now()->subMonths($this->faker->numberBetween(0, 12))->toDateString(),
            'domain_rank' => $this->faker->numberBetween(0, 1000),
            'is_lost' => false,
        ];
    }
}
