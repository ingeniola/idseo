<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataForSeo\Enums\EndpointGroup;
use App\Models\CostLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostLedger>
 */
class CostLedgerFactory extends Factory
{
    protected $model = CostLedger::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'client_id' => null,
            'project_id' => null,
            'endpoint_group' => $this->faker->randomElement(EndpointGroup::cases()),
            'cost' => $this->faker->randomFloat(6, 0, 5),
            'task_reference' => $this->faker->uuid(),
        ];
    }
}
