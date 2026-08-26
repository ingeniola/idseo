<?php

declare(strict_types=1);

namespace App\DataForSeo\KeywordData;

use App\DataForSeo\DataForSeoClient;
use App\Models\Keyword;
use Illuminate\Database\Eloquent\Collection;

/**
 * Enriquecimiento de volumen de búsqueda bajo demanda (sección 5.2 del
 * SPEC), vía keywords_data/google_ads/search_volume/live: modo Live
 * porque es una consulta interactiva disparada por una persona en
 * pantalla, nunca automática (sección 3.2). Cobra por llamada sin
 * importar el número de keywords (hasta 1000), así que agrupa todas
 * las keywords seleccionadas por (location_code, language_code) en una
 * sola llamada con una tarea por combinación, en vez de una llamada
 * por keyword.
 *
 * El campo `competition` de la respuesta es un nivel de texto
 * (LOW/MEDIUM/HIGH) según la documentación pública disponible; se usa
 * `competition_index` (0-100) normalizado a 0-1 para que calce con la
 * columna decimal(3,2) existente. Verificar contra una respuesta real
 * antes de confiar en este mapeo en producción.
 */
class EnrichKeywordVolumes
{
    private const ENDPOINT = 'keywords_data/google_ads/search_volume/live';

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    /**
     * @param  Collection<int, Keyword>  $keywords
     */
    public function execute(Collection $keywords): KeywordVolumeEnrichmentResult
    {
        if ($keywords->isEmpty()) {
            return new KeywordVolumeEnrichmentResult(requested: 0, updated: 0);
        }

        /** @var array<string, array{keywords: array<int, string>, location_code: int, language_code: string}> $groups */
        $groups = [];

        foreach ($keywords as $keyword) {
            $groupKey = $keyword->location_code.'|'.$keyword->language_code;

            $groups[$groupKey]['keywords'][] = $keyword->keyword;
            $groups[$groupKey]['location_code'] = $keyword->location_code;
            $groups[$groupKey]['language_code'] = $keyword->language_code;
        }

        $tasks = array_map(
            fn (array $group) => [...$group, 'keywords' => array_values(array_unique($group['keywords']))],
            array_values($groups),
        );

        $response = $this->client->post(self::ENDPOINT, $tasks);

        $updated = 0;

        foreach ($response->tasks as $task) {
            foreach ($task->result ?? [] as $row) {
                $match = $keywords->first(
                    fn (Keyword $keyword) => $keyword->keyword === ($row['keyword'] ?? null)
                        && $keyword->location_code === ($row['location_code'] ?? null)
                        && $keyword->language_code === ($row['language_code'] ?? null),
                );

                if ($match === null) {
                    continue;
                }

                $match->update([
                    'search_volume' => $row['search_volume'] ?? null,
                    'cpc' => $row['cpc'] ?? null,
                    'competition' => isset($row['competition_index']) ? $row['competition_index'] / 100 : null,
                    'volume_updated_at' => now(),
                ]);

                $updated++;
            }
        }

        return new KeywordVolumeEnrichmentResult(requested: $keywords->count(), updated: $updated);
    }
}
