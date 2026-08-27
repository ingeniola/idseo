<?php

declare(strict_types=1);

namespace App\DataForSeo\Backlinks;

use App\DataForSeo\DataForSeoClient;
use App\Models\Backlink;
use App\Models\BacklinkSummary;
use App\Models\Project;
use App\Models\ReferringDomain;
use Illuminate\Support\Carbon;

/**
 * Perfil de backlinks del propio dominio del proyecto (sección 5.1 del
 * SPEC de Fase 3: "perfil, dominios referentes, enlaces perdidos").
 * Modo Live porque es una consulta interactiva disparada por una
 * persona en pantalla — la sección 3.2 del SPEC prohíbe disparar Live
 * de forma automática o en bucle, así que este perfil se actualiza a
 * demanda, no en un job programado.
 *
 * Tres llamadas (summary, referring_domains, backlinks), cada una
 * verificada por separado contra la documentación pública disponible
 * (docs.dataforseo.com bloqueado en este entorno — verificado por
 * búsqueda cruzada, igual que en los módulos 2 y 4 de la Fase 2):
 *
 * - backlinks/summary/live: request {target, internal_list_limit,
 *   backlinks_status_type}, respuesta con target/rank/backlinks/
 *   referring_domains/referring_ips/broken_backlinks (nombres
 *   confirmados; "rank" es el nombre real del campo, se guarda en la
 *   columna domain_rank de la sección 4 del SPEC).
 * - backlinks/referring_domains/live: envelope result[0].items igual
 *   que los endpoints de Labs (Fase 2); cada item con domain/rank/
 *   backlinks/first_seen.
 * - backlinks/backlinks/live: mismo envelope; cada item con
 *   domain_from/url_from/url_to/anchor/dofollow/first_seen/last_seen/
 *   is_lost/domain_from_rank — is_lost SÍ viene confirmado del lado de
 *   la API para esta tabla, a diferencia de referring_domains (ver el
 *   docblock de esa migración: no se marca is_lost ahí por falta de
 *   confirmación, para no inventar una señal de "perdido" que en
 *   realidad solo sea "fuera del límite de esta página").
 */
class SyncBacklinkProfile
{
    private const SUMMARY_ENDPOINT = 'backlinks/summary/live';

    private const REFERRING_DOMAINS_ENDPOINT = 'backlinks/referring_domains/live';

    private const BACKLINKS_ENDPOINT = 'backlinks/backlinks/live';

    private const LIST_LIMIT = 100;

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    public function execute(Project $project): void
    {
        $domain = $project->domain;

        $this->syncSummary($project, $domain);
        $this->syncReferringDomains($project, $domain);
        $this->syncBacklinks($project, $domain);
    }

    private function syncSummary(Project $project, string $domain): void
    {
        $response = $this->client->post(self::SUMMARY_ENDPOINT, [[
            'target' => $domain,
            'internal_list_limit' => 10,
            'backlinks_status_type' => 'live',
        ]]);

        $result = $response->tasks[0]->result[0] ?? null;

        if (! is_array($result)) {
            return;
        }

        BacklinkSummary::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'domain' => $domain,
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

    private function syncReferringDomains(Project $project, string $domain): void
    {
        $response = $this->client->post(self::REFERRING_DOMAINS_ENDPOINT, [[
            'target' => $domain,
            'limit' => self::LIST_LIMIT,
        ]]);

        $items = $response->tasks[0]->result[0]['items'] ?? [];

        foreach ($items as $item) {
            $referringDomain = $item['domain'] ?? null;

            if (! is_string($referringDomain) || $referringDomain === '') {
                continue;
            }

            ReferringDomain::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'domain' => $referringDomain,
                ],
                [
                    'backlinks_count' => (int) ($item['backlinks'] ?? 0),
                    'first_seen' => isset($item['first_seen'])
                        ? Carbon::parse($item['first_seen'])->toDateString()
                        : Carbon::now()->toDateString(),
                    'domain_rank' => isset($item['rank']) ? (int) $item['rank'] : null,
                    'is_lost' => false,
                ],
            );
        }
    }

    private function syncBacklinks(Project $project, string $domain): void
    {
        $response = $this->client->post(self::BACKLINKS_ENDPOINT, [[
            'target' => $domain,
            'limit' => self::LIST_LIMIT,
        ]]);

        $items = $response->tasks[0]->result[0]['items'] ?? [];

        foreach ($items as $item) {
            $sourceUrl = $item['url_from'] ?? null;
            $targetUrl = $item['url_to'] ?? null;

            if (! is_string($sourceUrl) || $sourceUrl === '' || ! is_string($targetUrl) || $targetUrl === '') {
                continue;
            }

            Backlink::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'link_hash' => Backlink::hashFor($sourceUrl, $targetUrl),
                ],
                [
                    'source_url' => $sourceUrl,
                    'source_domain' => (string) ($item['domain_from'] ?? ''),
                    'target_url' => $targetUrl,
                    'anchor' => $item['anchor'] ?? null,
                    'dofollow' => (bool) ($item['dofollow'] ?? true),
                    'first_seen' => isset($item['first_seen'])
                        ? Carbon::parse($item['first_seen'])->toDateString()
                        : Carbon::now()->toDateString(),
                    'last_seen' => isset($item['last_seen'])
                        ? Carbon::parse($item['last_seen'])->toDateString()
                        : Carbon::now()->toDateString(),
                    'is_lost' => (bool) ($item['is_lost'] ?? false),
                    'domain_rank' => isset($item['domain_from_rank']) ? (int) $item['domain_from_rank'] : null,
                ],
            );
        }
    }
}
