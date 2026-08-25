<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Models\DataForSeoTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataForSeoTask>
 */
class DataForSeoTaskFactory extends Factory
{
    protected $model = DataForSeoTask::class;

    public function definition(): array
    {
        return [
            'task_id' => $this->faker->unique()->uuid(),
            'endpoint' => 'serp/google/organic/task_post',
            'status' => DataForSeoTaskStatus::Pending,
            'payload_sent' => ['keyword' => $this->faker->words(3, true)],
            'payload_received' => null,
            'cost' => null,
            'posted_at' => now(),
            'completed_at' => null,
            'error_message' => null,
            'retry_count' => 0,
        ];
    }
}
