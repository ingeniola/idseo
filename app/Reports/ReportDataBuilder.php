<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Keyword;
use App\Models\Project;
use App\Models\ProjectVisibilitySnapshot;
use App\Models\Ranking;
use App\Models\Report;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Arma los datos de un Report (sección 5.4 del SPEC): resumen
 * ejecutivo, evolución de visibilidad, tabla de posiciones, top
 * ganancias/pérdidas y keywords nuevas en top 10, más la comparativa
 * contra el período anterior. No sabe nada de PDF ni de Blade — solo
 * consulta y calcula.
 */
class ReportDataBuilder
{
    private const TOP_N = 10;

    public function build(Report $report): ReportData
    {
        $project = $report->project;
        $client = $project->client;
        $template = $report->template;

        $periodStart = Carbon::parse($report->period_start)->startOfDay();
        $periodEnd = Carbon::parse($report->period_end)->startOfDay();

        [$previousPeriodStart, $previousPeriodEnd] = $this->previousPeriod($periodStart, $periodEnd);

        $currentMetrics = $this->metricsAsOf($project, $periodEnd);
        $previousMetrics = $this->metricsAsOf($project, $previousPeriodEnd);

        $visibilityEvolution = $project->visibilitySnapshots()
            ->whereBetween('calculated_at', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('calculated_at')
            ->get(['calculated_at', 'visibility_score'])
            ->map(fn (ProjectVisibilitySnapshot $snapshot) => [
                'date' => $snapshot->calculated_at,
                'visibility_score' => (float) $snapshot->visibility_score,
            ])
            ->values();

        $positions = $this->buildKeywordPositions($project, $periodStart, $periodEnd);

        $withDelta = $positions->filter(fn (ReportKeywordPosition $position) => $position->delta() !== null);

        $topGains = $withDelta
            ->filter(fn (ReportKeywordPosition $position) => $position->delta() > 0)
            ->sortByDesc(fn (ReportKeywordPosition $position) => $position->delta())
            ->take(self::TOP_N)
            ->values();

        $topLosses = $withDelta
            ->filter(fn (ReportKeywordPosition $position) => $position->delta() < 0)
            ->sortBy(fn (ReportKeywordPosition $position) => $position->delta())
            ->take(self::TOP_N)
            ->values();

        $newKeywordsInTop10 = $positions
            ->filter(fn (ReportKeywordPosition $position) => $position->enteredTop10())
            ->values();

        $brandingOverrides = $template->branding_overrides ?? [];

        return new ReportData(
            report: $report,
            project: $project,
            client: $client,
            template: $template,
            currentMetrics: $currentMetrics,
            previousMetrics: $previousMetrics,
            visibilityEvolution: $visibilityEvolution,
            visibilityChart: (new SvgLineChart)->build($visibilityEvolution),
            positions: $positions,
            topGains: $topGains,
            topLosses: $topLosses,
            newKeywordsInTop10: $newKeywordsInTop10,
            logoDataUri: $this->logoDataUri($brandingOverrides['logo_path'] ?? $client->logo_path),
            primaryColor: $brandingOverrides['primary_color'] ?? $client->primary_color ?? '#111827',
        );
    }

    /**
     * El logo se guarda como archivo en disco (Filament FileUpload,
     * sección 5.1); Chromium (Browsershot) renderiza el HTML en un
     * proceso aparte que no tiene por qué ver la misma ruta local, y
     * exponer el disco por HTTP solo para esto sería más superficie
     * de la necesaria. Un data URI embebido evita ambos problemas.
     */
    private function logoDataUri(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk(config('filament.default_filesystem_disk', 'local'));

        if (! $disk->exists($path)) {
            return null;
        }

        $mimeType = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($path));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousPeriod(Carbon $periodStart, Carbon $periodEnd): array
    {
        $days = $periodStart->diffInDays($periodEnd) + 1;
        $previousEnd = $periodStart->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [$previousStart, $previousEnd];
    }

    private function metricsAsOf(Project $project, Carbon $date): ReportPeriodMetrics
    {
        $snapshot = $project->visibilitySnapshots()
            ->where('calculated_at', '<=', $date->toDateString())
            ->orderByDesc('calculated_at')
            ->first();

        if ($snapshot === null) {
            return ReportPeriodMetrics::empty();
        }

        return new ReportPeriodMetrics(
            asOfDate: $snapshot->calculated_at,
            visibilityScore: (float) $snapshot->visibility_score,
            trackedKeywordsCount: $snapshot->tracked_keywords_count,
            keywordsInTop3: $snapshot->keywords_in_top_3,
            keywordsInTop10: $snapshot->keywords_in_top_10,
            keywordsInTop20: $snapshot->keywords_in_top_20,
            averagePosition: $snapshot->average_position !== null ? (float) $snapshot->average_position : null,
        );
    }

    /**
     * Una sola consulta de rankings para todas las keywords activas
     * del proyecto, agrupada en PHP por keyword — evita N+1 al buscar
     * la posición de cada keyword al inicio y al final del período.
     *
     * @return Collection<int, ReportKeywordPosition>
     */
    private function buildKeywordPositions(Project $project, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $keywords = $project->keywords()->where('is_active', true)->get();

        $rankingsByKeyword = Ranking::query()
            ->whereIn('keyword_id', $keywords->pluck('id'))
            ->where('checked_at', '<=', $periodEnd->toDateString())
            ->orderBy('checked_at')
            ->get()
            ->groupBy('keyword_id');

        return $keywords->map(function (Keyword $keyword) use ($rankingsByKeyword, $periodStart) {
            /** @var Collection<int, Ranking> $history */
            $history = $rankingsByKeyword->get($keyword->id, new Collection);

            $atStart = $history->last(fn (Ranking $ranking) => $ranking->checked_at <= $periodStart->toDateString());
            $atEnd = $history->last();

            return new ReportKeywordPosition(
                keyword: $keyword->keyword,
                positionStart: $atStart?->position,
                positionEnd: $atEnd?->position,
                url: $atEnd?->url,
                searchVolume: $keyword->search_volume,
            );
        })->values();
    }
}
