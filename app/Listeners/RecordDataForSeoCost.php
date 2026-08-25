<?php

declare(strict_types=1);

namespace App\Listeners;

use App\DataForSeo\Enums\EndpointGroup;
use App\DataForSeo\Events\DataForSeoRequestCompleted;
use App\Models\CostLedger;
use Illuminate\Support\Carbon;

/**
 * Persiste cada respuesta con status_code parseable en `cost_ledger`,
 * incluyendo las de costo 0: sección 3.5 del SPEC pide registrar el
 * costo "siempre". client_id/project_id quedan null aquí: el cliente
 * HTTP no conoce el contexto de negocio que originó la llamada; esa
 * atribución se conecta cuando existan los Jobs que sí lo conocen
 * (Fase 1, paso 8).
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
            'client_id' => null,
            'project_id' => null,
            'endpoint_group' => EndpointGroup::fromEndpoint($event->endpoint),
            'cost' => $event->cost ?? 0,
            'task_reference' => null,
        ]);
    }
}
