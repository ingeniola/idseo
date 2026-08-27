<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchConsoleConnection>
 */
class SearchConsoleConnectionFactory extends Factory
{
    protected $model = SearchConsoleConnection::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'site_url' => 'https://'.$this->faker->domainName().'/',
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'expires_at' => now()->addHour(),
            'connected_by' => User::factory(),
        ];
    }
}
