<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\ReportSection;
use App\Models\Client;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportTemplate;
use Illuminate\Support\Collection;

/**
 * Todo lo que necesita resources/views/reports/pdf.blade.php para
 * renderizar un reporte, ya calculado — la plantilla Blade no hace
 * consultas ni cálculos, solo pinta lo que trae este objeto.
 */
final class ReportData
{
    /**
     * @param  Collection<int, array{date: string, visibility_score: float}>  $visibilityEvolution
     * @param  array{width: int, height: int, polyline: string, dots: array<int, array{x: float, y: float}>, labels: array<int, array{x: float, label: string}>}  $visibilityChart
     * @param  Collection<int, ReportKeywordPosition>  $positions
     * @param  Collection<int, ReportKeywordPosition>  $topGains
     * @param  Collection<int, ReportKeywordPosition>  $topLosses
     * @param  Collection<int, ReportKeywordPosition>  $newKeywordsInTop10
     */
    public function __construct(
        public readonly Report $report,
        public readonly Project $project,
        public readonly Client $client,
        public readonly ReportTemplate $template,
        public readonly ReportPeriodMetrics $currentMetrics,
        public readonly ReportPeriodMetrics $previousMetrics,
        public readonly Collection $visibilityEvolution,
        public readonly array $visibilityChart,
        public readonly Collection $positions,
        public readonly Collection $topGains,
        public readonly Collection $topLosses,
        public readonly Collection $newKeywordsInTop10,
        public readonly ?string $logoDataUri,
        public readonly string $primaryColor,
    ) {}

    public function hasSection(ReportSection $section): bool
    {
        return in_array($section->value, $this->template->sections, true);
    }
}
