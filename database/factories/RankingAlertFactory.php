<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AlertType;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\RankingAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RankingAlert>
 */
class RankingAlertFactory extends Factory
{
    protected $model = RankingAlert::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'keyword_id' => Keyword::factory(),
            'type' => AlertType::PositionDrop,
            'previous_position' => 5,
            'current_position' => 15,
            'triggered_at' => now()->toDateString(),
            'notified_at' => null,
            'created_at' => now(),
        ];
    }
}
