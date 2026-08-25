<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DataForSeoRequestLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataForSeoRequestLog>
 */
class DataForSeoRequestLogFactory extends Factory
{
    protected $model = DataForSeoRequestLog::class;

    public function definition(): array
    {
        return [
            'method' => 'POST',
            'endpoint' => 'serp/google/organic/task_post',
            'duration_ms' => $this->faker->numberBetween(20, 500),
            'http_status' => 200,
            'api_status_code' => 20000,
            'cost' => $this->faker->randomFloat(6, 0, 1),
            'created_at' => now(),
        ];
    }
}
