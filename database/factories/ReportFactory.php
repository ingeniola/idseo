<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        $periodStart = now()->startOfMonth();

        return [
            'project_id' => Project::factory(),
            'template_id' => ReportTemplate::factory(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
            'status' => ReportStatus::Pending,
            'file_path' => null,
            'generated_by' => null,
            'sent_at' => null,
        ];
    }
}
