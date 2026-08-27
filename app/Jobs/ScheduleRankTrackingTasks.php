<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\RankTracking\DueKeywordsFinder;
use App\RankTracking\PostRankTrackingTasks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Job diario 02:00 (sección 6 del SPEC): recorre proyectos activos y
 * publica task_post para las keywords que están "due" según la
 * frecuencia del proyecto (ver DueKeywordsFinder). El envío en sí
 * (chunking, postback_url, creación de DataForSeoTask) vive en
 * PostRankTrackingTasks, compartido con el disparo inmediato al
 * agregar keywords nuevas (KeywordsRelationManager,
 * TrackNewlyImportedKeywords).
 */
class ScheduleRankTrackingTasks implements ShouldQueue
{
    use Queueable;

    public function handle(PostRankTrackingTasks $poster, DueKeywordsFinder $dueKeywordsFinder): void
    {
        Project::query()
            ->where('is_active', true)
            ->each(fn (Project $project) => $poster->execute($project, $dueKeywordsFinder->forProject($project)));
    }
}
