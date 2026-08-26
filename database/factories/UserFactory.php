<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Analyst,
            'client_id' => null,
            // 2FA obligatorio para el panel interno (sección 9 y Fase 1
            // paso 12 del SPEC): un usuario de fábrica "listo para
            // usar" ya lo tiene configurado, para que actingAs() en
            // las pruebas no choque contra la página de configuración
            // requerida de Filament. No es un secreto TOTP real — solo
            // necesita no estar vacío, porque eso es lo único que
            // AppAuthentication::isEnabled() comprueba.
            'app_authentication_secret' => Str::random(32),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Para probar explícitamente el flujo de "todavía no configuró 2FA".
     */
    public function withoutMultiFactorAuthentication(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_authentication_secret' => null,
        ]);
    }
}
