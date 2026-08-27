<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BacklinkSummary;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacklinkSummary>
 */
class BacklinkSummaryFactory extends Factory
{
    protected $model = BacklinkSummary::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'domain' => $this->faker->domainName(),
            'captured_at' => now()->toDateString(),
            'total_backlinks' => $this->faker->numberBetween(0, 100000),
            'referring_domains' => $this->faker->numberBetween(0, 5000),
            'referring_ips' => $this->faker->numberBetween(0, 5000),
            'domain_rank' => $this->faker->numberBetween(0, 1000),
            'broken_backlinks' => $this->faker->numberBetween(0, 500),
            'raw' => null,
        ];
    }
}
