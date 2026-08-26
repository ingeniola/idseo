<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectVisibilitySnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Job diario 06:00 (sección 6 del SPEC): métricas derivadas de
 * `rankings`, sin costo de API. Usa la última posición conocida de
 * cada keyword activa (no solo las revisadas hoy: no todas las
 * keywords se revisan todos los días según su tracking_frequency).
 *
 * visibility_score es un índice ponderado 0-100 (no una métrica que
 * DataForSEO devuelva): top 1-3 pesa 1.0, 4-10 pesa 0.5, 11-20 pesa
 * 0.2, el resto (o sin ranking) pesa 0. Es una elección de diseño
 * razonable, no un estándar de la industria; documentado para poder
 * ajustarla si el negocio prefiere otra fórmula.
 */
class CalculateProjectVisibility implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Project::query()
            ->where('is_active', true)
            ->each(fn (Project $project) => $this->calculateFor($project));
    }

    private function calculateFor(Project $project): void
    {
        $positions = $project->keywords()
            ->where('is_active', true)
            ->with('latestRanking')
            ->get()
            ->map(fn ($keyword) => $keyword->latestRanking?->position)
            ->filter(fn (?int $position) => $position !== null)
            ->values();

        $trackedKeywordsCount = $positions->count();

        $weight = fn (int $position) => match (true) {
            $position <= 3 => 1.0,
            $position <= 10 => 0.5,
            $position <= 20 => 0.2,
            default => 0.0,
        };

        $visibilityScore = $trackedKeywordsCount > 0
            ? round(($positions->sum($weight) / $trackedKeywordsCount) * 100, 2)
            : 0.0;

        ProjectVisibilitySnapshot::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'calculated_at' => Carbon::now()->toDateString(),
            ],
            [
                'visibility_score' => $visibilityScore,
                'tracked_keywords_count' => $trackedKeywordsCount,
                'keywords_in_top_3' => $positions->filter(fn (int $p) => $p <= 3)->count(),
                'keywords_in_top_10' => $positions->filter(fn (int $p) => $p <= 10)->count(),
                'keywords_in_top_20' => $positions->filter(fn (int $p) => $p <= 20)->count(),
                'average_position' => $trackedKeywordsCount > 0 ? round($positions->avg(), 2) : null,
            ],
        );
    }
}
