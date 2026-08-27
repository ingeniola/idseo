<?php

declare(strict_types=1);

namespace App\DataForSeo\Backlinks;

use App\DataForSeo\DataForSeoClient;
use App\Models\BacklinkSummary;
use App\Models\Project;
use Illuminate\Support\Carbon;

/**
 * "Comparativa contra competidores" (sección 5.1 de Fase 3 del SPEC):
 * a diferencia de SyncBacklinkProfile, solo trae el resumen
 * (backlinks/summary/live) de un dominio competidor — no su lista
 * completa de backlinks/dominios referentes, que sería un alcance
 * mucho más caro y no es lo que pide "comparativa". Se guarda en la
 * misma tabla backlink_summaries con `domain` = el dominio del
 * competidor, para mostrarlo lado a lado con el perfil propio en la
 * UI (ver el docblock de esa migración).
 */
class CompareCompetitorBacklinks
{
    private const ENDPOINT = 'backlinks/summary/live';

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    public function execute(Project $project, string $competitorDomain): BacklinkSummary
    {
        $response = $this->client->post(self::ENDPOINT, [[
            'target' => $competitorDomain,
            'internal_list_limit' => 10,
            'backlinks_status_type' => 'live',
        ]], $project->id);

        $result = $response->tasks[0]->result[0] ?? [];

        return BacklinkSummary::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'domain' => $competitorDomain,
                'captured_at' => Carbon::now()->toDateString(),
            ],
            [
                'total_backlinks' => (int) ($result['backlinks'] ?? 0),
                'referring_domains' => (int) ($result['referring_domains'] ?? 0),
                'referring_ips' => (int) ($result['referring_ips'] ?? 0),
                'domain_rank' => isset($result['rank']) ? (int) $result['rank'] : null,
                'broken_backlinks' => (int) ($result['broken_backlinks'] ?? 0),
                'raw' => $result,
            ],
        );
    }
}
