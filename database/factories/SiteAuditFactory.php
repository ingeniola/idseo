<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Models\Project;
use App\Models\SiteAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteAudit>
 */
class SiteAuditFactory extends Factory
{
    protected $model = SiteAudit::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'task_id' => $this->faker->uuid(),
            'status' => DataForSeoTaskStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'pages_crawled' => $this->faker->numberBetween(1, 500),
            'onpage_score' => $this->faker->randomFloat(2, 0, 100),
            'cost' => $this->faker->randomFloat(6, 0, 1),
        ];
    }
}
