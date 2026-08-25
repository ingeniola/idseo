<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportTemplate>
 */
class ReportTemplateFactory extends Factory
{
    protected $model = ReportTemplate::class;

    public function definition(): array
    {
        return [
            'client_id' => null,
            'name' => $this->faker->words(2, true).' template',
            'sections' => ['resumen_ejecutivo', 'evolucion_visibilidad', 'tabla_posiciones'],
            'branding_overrides' => null,
        ];
    }
}
