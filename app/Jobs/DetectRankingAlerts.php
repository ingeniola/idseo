<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AlertType;
use App\Models\Project;
use App\Models\Ranking;
use App\Models\RankingAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Job diario 06:30 (sección 6 del SPEC, Fase 2 "Alertas"): evalúa las
 * 4 reglas de la sección 5 (caída de más de N posiciones, salida del
 * top 10, entrada al top 3, pérdida de featured snippet) comparando
 * las dos últimas Ranking de cada keyword activa. Costo cero — no
 * llama a DataForSEO, solo lee `rankings` que ya escribió
 * ProcessDataForSeoPostback. Corre después de CalculateSerpCompetitors
 * (06:15), junto con los otros jobs "derivados diarios sin costo".
 *
 * Audiencia de las alertas: usuarios internos asignados al proyecto
 * (Project::users()), no el cliente — la sección 5.5 "Portal de
 * cliente" enumera explícitamente lo que el cliente puede ver y no
 * menciona alertas, y este job vive junto a jobs claramente
 * internos/operativos en la sección 6 (ej. SyncAccountBalance). Es una
 * decisión de diseño razonable, no algo que el SPEC dicte
 * literalmente.
 */
class DetectRankingAlerts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Project::query()
            ->where('is_active', true)
            ->each(fn (Project $project) => $this->detectFor($project));
    }

    private function detectFor(Project $project): void
    {
        $today = Carbon::now()->toDateString();
        $hasNewAlerts = false;

        $keywords = $project->keywords()->where('is_active', true)->get();

        foreach ($keywords as $keyword) {
            /** @var Collection<int, Ranking> $lastTwo */
            $lastTwo = $keyword->rankings()->orderByDesc('checked_at')->limit(2)->get();

            $current = $lastTwo->first();
            $previous = $lastTwo->get(1);

            if ($current === null || $previous === null) {
                continue;
            }

            foreach ($this->evaluateRules($current, $previous) as $type) {
                // firstOrCreate por (keyword_id, type, triggered_at): si el
                // job corre dos veces el mismo día sin una Ranking nueva de
                // por medio, no duplica la alerta ni la vuelve a notificar.
                $alert = RankingAlert::query()->firstOrCreate(
                    [
                        'keyword_id' => $keyword->id,
                        'type' => $type->value,
                        'triggered_at' => $today,
                    ],
                    [
                        'project_id' => $project->id,
                        'previous_position' => $previous->position,
                        'current_position' => $current->position,
                        'created_at' => now(),
                    ],
                );

                if ($alert->wasRecentlyCreated) {
                    $hasNewAlerts = true;
                }
            }
        }

        if ($hasNewAlerts) {
            SendRankingAlertDigest::dispatch($project->id);
        }
    }

    /**
     * @return array<int, AlertType>
     */
    private function evaluateRules(Ranking $current, Ranking $previous): array
    {
        $threshold = (int) config('alerts.position_drop_threshold');
        $types = [];

        if ($current->position !== null && $previous->position !== null
            && ($current->position - $previous->position) >= $threshold) {
            $types[] = AlertType::PositionDrop;
        }

        $wasTop10 = $previous->position !== null && $previous->position <= 10;
        $isTop10 = $current->position !== null && $current->position <= 10;

        if ($wasTop10 && ! $isTop10) {
            $types[] = AlertType::ExitedTop10;
        }

        $wasTop3 = $previous->position !== null && $previous->position <= 3;
        $isTop3 = $current->position !== null && $current->position <= 3;

        if (! $wasTop3 && $isTop3) {
            $types[] = AlertType::EnteredTop3;
        }

        if ($previous->is_featured_snippet && ! $current->is_featured_snippet) {
            $types[] = AlertType::LostFeaturedSnippet;
        }

        return $types;
    }
}
