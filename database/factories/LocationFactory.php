<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'location_code' => $this->faker->unique()->numberBetween(1000, 999999),
            'location_name' => $this->faker->city(),
            'location_name_canonical' => $this->faker->city(),
            'country_iso_code' => $this->faker->countryCode(),
            'location_type' => 'City',
            'parent_code' => null,
        ];
    }
}
