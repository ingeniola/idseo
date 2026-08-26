<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'event' => AuditEvent::LoginSucceeded,
            'user_id' => User::factory(),
            'email' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'context' => null,
            'created_at' => now(),
        ];
    }
}
