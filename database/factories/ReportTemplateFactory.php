<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportSection;
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
            'sections' => [
                ReportSection::ExecutiveSummary->value,
                ReportSection::VisibilityEvolution->value,
                ReportSection::PositionsTable->value,
                ReportSection::TopGains->value,
                ReportSection::TopLosses->value,
                ReportSection::NewKeywordsInTop10->value,
            ],
            'branding_overrides' => null,
        ];
    }
}
