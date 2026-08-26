<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Keyword;
use App\Models\Project;
use App\Models\SerpCompetitor;
use App\RankTracking\SerpResultParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Job diario 06:15 (sección 6 del SPEC, Fase 2 "Análisis de
 * competidores derivado de los SERP snapshots"): agrega, por dominio,
 * los resultados orgánicos del último SERP snapshot de cada keyword
 * activa del proyecto. Costo cero — no llama a DataForSEO, solo lee
 * `serp_snapshots.top_results` que ya guardó ProcessDataForSeoPostback.
 *
 * visibility_score reutiliza exactamente la misma fórmula ponderada de
 * CalculateProjectVisibility (top 1-3 = 1.0, top 4-10 = 0.5, top 11-20
 * = 0.2, resto = 0), sobre el mismo total de keywords rastreadas del
 * proyecto — así el score de un competidor es directamente comparable
 * con el visibility_score del propio proyecto.
 *
 * Corre después de CalculateProjectVisibility (06:00) porque no
 * depende de ella, solo comparte el horario "derivados, sin costo".
 */
class CalculateSerpCompetitors implements ShouldQueue
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
        $trackedKeywords = $project->keywords()
            ->where('is_active', true)
            ->with('latestSerpSnapshot')
            ->get();

        $trackedKeywordsCount = $trackedKeywords->count();

        /** @var array<string, array{positions: array<int, int>, keywordIds: array<int, true>}> $domainStats */
        $domainStats = [];

        foreach ($trackedKeywords as $keyword) {
            foreach ($this->organicCompetitorsFor($keyword, $project->domain) as $occurrence) {
                $domainStats[$occurrence['domain']] ??= ['positions' => [], 'keywordIds' => []];
                $domainStats[$occurrence['domain']]['positions'][] = $occurrence['position'];
                $domainStats[$occurrence['domain']]['keywordIds'][$keyword->id] = true;
            }
        }

        $today = Carbon::now()->toDateString();
        $weight = fn (int $position) => match (true) {
            $position <= 3 => 1.0,
            $position <= 10 => 0.5,
            $position <= 20 => 0.2,
            default => 0.0,
        };

        // Un dominio que ya no aparece hoy no debe dejar una fila
        // fantasma de una corrida anterior en el mismo día (el job
        // puede correr más de una vez el mismo día — ver la prueba de
        // idempotencia). whereNotIn con un arreglo vacío borra todo lo
        // de hoy, que es lo correcto si ya no se detectó ningún
        // competidor.
        SerpCompetitor::query()
            ->where('project_id', $project->id)
            ->where('calculated_at', $today)
            ->whereNotIn('domain', array_keys($domainStats))
            ->delete();

        foreach ($domainStats as $domain => $stats) {
            $positions = collect($stats['positions']);

            $visibilityScore = $trackedKeywordsCount > 0
                ? round(($positions->sum($weight) / $trackedKeywordsCount) * 100, 2)
                : 0.0;

            SerpCompetitor::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'domain' => $domain,
                    'calculated_at' => $today,
                ],
                [
                    'keywords_overlap' => count($stats['keywordIds']),
                    'avg_position' => round($positions->avg(), 2),
                    'visibility_score' => $visibilityScore,
                ],
            );
        }
    }

    /**
     * Dominios (normalizados) y posiciones orgánicas del último SERP
     * snapshot de la keyword, excluyendo el propio dominio del
     * proyecto.
     *
     * @return array<int, array{domain: string, position: int}>
     */
    private function organicCompetitorsFor(Keyword $keyword, string $projectDomain): array
    {
        $snapshot = $keyword->latestSerpSnapshot;

        if ($snapshot === null) {
            return [];
        }

        $occurrences = [];

        foreach ($snapshot->top_results as $item) {
            if (($item['type'] ?? null) !== 'organic') {
                continue;
            }

            $domain = (string) ($item['domain'] ?? '');
            $rankGroup = $item['rank_group'] ?? null;

            if ($domain === '' || $rankGroup === null) {
                continue;
            }

            if (SerpResultParser::domainsMatch($domain, $projectDomain)) {
                continue;
            }

            $occurrences[] = [
                'domain' => SerpResultParser::normalizeDomain($domain),
                'position' => (int) $rankGroup,
            ];
        }

        return $occurrences;
    }
}
