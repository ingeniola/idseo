<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Filament\Imports\KeywordImporter;
use App\Jobs\TrackKeywordsNow;
use App\Models\Keyword;
use Filament\Actions\Imports\Events\ImportCompleted;

/**
 * Dispara el rastreo inmediato (TrackKeywordsNow) para las keywords
 * que acaba de traer una importación CSV, en vez de esperar al corte
 * diario 02:00 — mismo criterio que el alta individual y el pegado
 * masivo (KeywordsRelationManager).
 *
 * Filament procesa un import en chunks vía jobs en cola, así que no
 * hay forma barata de acumular los IDs exactos fila por fila; en
 * cambio, al terminar el import completo se buscan las keywords del
 * proyecto que nunca se han rastreado (mismo criterio de "due" que
 * DueKeywordsFinder para una keyword nueva) creadas desde que empezó
 * este import — evita re-disparar keywords viejas sin rastrear por
 * otra razón, que no son responsabilidad de esta importación.
 */
class TrackNewlyImportedKeywords
{
    public function handle(ImportCompleted $event): void
    {
        if ($event->getImport()->importer !== KeywordImporter::class) {
            return;
        }

        $projectId = $event->getOptions()['project_id'] ?? null;

        if ($projectId === null) {
            return;
        }

        $keywordIds = Keyword::query()
            ->where('project_id', $projectId)
            ->where('created_at', '>=', $event->getImport()->created_at)
            ->doesntHave('rankings')
            ->pluck('id')
            ->all();

        if ($keywordIds === []) {
            return;
        }

        TrackKeywordsNow::dispatch($keywordIds);
    }
}
