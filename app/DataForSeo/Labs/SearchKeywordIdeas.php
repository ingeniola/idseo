<?php

declare(strict_types=1);

namespace App\DataForSeo\Labs;

use App\DataForSeo\DataForSeoClient;
use App\Models\KeywordResearchSession;
use App\Models\Project;
use App\Models\User;

/**
 * Investigación de keywords a partir de una keyword semilla (sección
 * 5.2 del SPEC), vía dataforseo_labs/google/keyword_ideas/live: modo
 * Live porque es una consulta interactiva disparada por una persona en
 * pantalla, nunca automática (sección 3.2), igual que
 * EnrichKeywordVolumes.
 *
 * Forma de la respuesta verificada contra la documentación pública
 * disponible (no contra una respuesta real, bloqueado por la red de
 * este entorno de desarrollo — verificar antes de usar en producción):
 * a diferencia de keywords_data/search_volume (result = arreglo plano
 * de filas), los endpoints de Labs anidan las filas en
 * result[0].items. Cada item trae `keyword` en la raíz y el resto de
 * las métricas en sub-objetos: keyword_info (search_volume, cpc,
 * competition), keyword_properties (keyword_difficulty), y
 * search_intent_info (main_intent) — este último confirmado para
 * keyword_overview/historical_keyword_data pero no confirmado con
 * certeza para keyword_ideas específicamente, así que se lee de forma
 * defensiva (null si no está presente, sin romper el resto del
 * mapeo).
 */
class SearchKeywordIdeas
{
    private const ENDPOINT = 'dataforseo_labs/google/keyword_ideas/live';

    private const LIMIT = 100;

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    public function execute(Project $project, ?User $user, string $seedKeyword): KeywordResearchSession
    {
        $task = [
            'keywords' => [$seedKeyword],
            'location_code' => $project->default_location_code,
            'language_code' => $project->default_language_code,
            'limit' => self::LIMIT,
        ];

        // Se manda exactamente una tarea, así que tasks[0] siempre está
        // presente cuando post() devuelve sin lanzar una excepción
        // (DataForSeoClient solo devuelve una respuesta exitosa).
        $response = $this->client->post(self::ENDPOINT, [$task]);
        $task0 = $response->tasks[0];

        /** @var array<int, array<string, mixed>> $items */
        $items = $task0->result[0]['items'] ?? [];

        $session = KeywordResearchSession::query()->create([
            'project_id' => $project->id,
            'user_id' => $user?->id,
            'seed_keyword' => $seedKeyword,
            'source_endpoint' => self::ENDPOINT,
            'cost' => $task0->cost,
            'created_at' => now(),
        ]);

        foreach ($items as $item) {
            $keyword = $item['keyword'] ?? null;

            if (! is_string($keyword) || $keyword === '') {
                continue;
            }

            $keywordInfo = $item['keyword_info'] ?? [];
            $keywordProperties = $item['keyword_properties'] ?? [];
            $searchIntentInfo = $item['search_intent_info'] ?? [];

            $session->keywordIdeas()->create([
                'keyword' => $keyword,
                'search_volume' => $keywordInfo['search_volume'] ?? null,
                'cpc' => $keywordInfo['cpc'] ?? null,
                // keyword_info.competition (a diferencia de
                // competition_index de keywords_data/search_volume) ya
                // viene 0-1 según la documentación pública, sin
                // necesitar normalización — verificar contra una
                // respuesta real antes de confiar en esto en producción.
                'competition' => $keywordInfo['competition'] ?? null,
                'difficulty' => $keywordProperties['keyword_difficulty'] ?? null,
                'intent' => $searchIntentInfo['main_intent'] ?? null,
            ]);
        }

        return $session;
    }
}
