<?php

declare(strict_types=1);

namespace App\RankTracking;

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Enums\TrackingFrequency;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Determina qué keywords activas de un proyecto activo están "due"
 * para un chequeo de ranking hoy, según tracking_frequency (sección
 * 5.3 del SPEC: "Job programado según la frecuencia del proyecto").
 * Excluye keywords que ya tienen una tarea pendiente para no duplicar
 * el trabajo en vuelo.
 */
class DueKeywordsFinder
{
    /**
     * @return Collection<int, Keyword>
     */
    public function forProject(Project $project, ?Carbon $today = null): Collection
    {
        $today = ($today ?? Carbon::today())->startOfDay();
        $minDaysSinceLastCheck = match ($project->tracking_frequency) {
            TrackingFrequency::Daily => 1,
            TrackingFrequency::Weekly => 7,
            TrackingFrequency::Monthly => 30,
        };

        $pendingKeywordIds = DataForSeoTask::query()
            ->where('taskable_type', Keyword::class)
            ->where('status', DataForSeoTaskStatus::Pending)
            ->pluck('taskable_id');

        return $project->keywords()
            ->where('is_active', true)
            ->whereNotIn('id', $pendingKeywordIds)
            ->with('latestRanking')
            ->get()
            ->filter(function (Keyword $keyword) use ($today, $minDaysSinceLastCheck) {
                $lastCheckedAt = $keyword->latestRanking?->checked_at;

                if ($lastCheckedAt === null) {
                    return true;
                }

                return Carbon::parse($lastCheckedAt)->startOfDay()->diffInDays($today) >= $minDaysSinceLastCheck;
            })
            ->values();
    }
}
