<?php

declare(strict_types=1);

namespace App\Listeners;

use App\DataForSeo\Enums\EndpointGroup;
use App\DataForSeo\Events\DataForSeoRequestCompleted;
use App\Models\CostLedger;
use App\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Persiste cada respuesta con status_code parseable en `cost_ledger`,
 * incluyendo las de costo 0: sección 3.5 del SPEC pide registrar el
 * costo "siempre".
 *
 * `project_id` viene del propio evento (lo pasa quien llamó a
 * DataForSeoClient::post()/get(), ver el docblock de esa clase);
 * `client_id` se resuelve aquí a partir del proyecto en vez de pedirle
 * a cada llamador que lo sepa también, para no duplicar esa relación
 * en 15+ sitios. Si `project_id` es null (llamada que abarca varios
 * proyectos, como tasks_ready, o ninguno, como sync-locations),
 * `client_id` también queda null — no se inventa una atribución.
 */
class RecordDataForSeoCost
{
    public function handle(DataForSeoRequestCompleted $event): void
    {
        if ($event->apiStatusCode === null) {
            return;
        }

        CostLedger::query()->create([
            'date' => Carbon::now()->toDateString(),
            'client_id' => $event->projectId !== null ? Project::query()->find($event->projectId)?->client_id : null,
            'project_id' => $event->projectId,
            'endpoint_group' => EndpointGroup::fromEndpoint($event->endpoint),
            'cost' => $event->cost ?? 0,
            'task_reference' => null,
        ]);
    }
}
