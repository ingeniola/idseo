<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DataForSeo\Data\DataForSeoResponseData;
use App\DataForSeo\DataForSeoClient;
use App\Models\Language;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Primera llamada real a la API (sección 11 del SPEC). Sincroniza el
 * espejo local de `locations`/`languages` desde `serp/google/locations`,
 * `serp/google/languages` y `keywords_data/google_ads/languages` —
 * endpoints de referencia sin costo, no el flujo task_post/task_get.
 *
 * `location_name_canonical` no tiene una fuente propia confirmada en el
 * payload de DataForSEO (no encontré un campo distinto de
 * `location_name` en la documentación pública disponible); se guarda
 * igual a `location_name` hasta verificar lo contrario contra la cuenta
 * real. Antes de prometerle SEO local a un cliente en Honduras,
 * Guatemala, El Salvador, Nicaragua, Costa Rica o Panamá, correr este
 * comando y revisar a mano qué granularidad (location_type) existe
 * para cada país — sección 4 y riesgo #1 del SPEC.
 */
class DataForSeoSyncLocationsCommand extends Command
{
    private const CHUNK_SIZE = 500;

    protected $signature = 'dataforseo:sync-locations
        {--country= : Filtra solo las ubicaciones de un país por su ISO code, ej. --country=HN}';

    protected $description = 'Sincroniza el catálogo local de ubicaciones e idiomas desde DataForSEO';

    public function handle(DataForSeoClient $client): int
    {
        $this->syncLanguages($client);
        $this->syncGoogleAdsKeywordLanguages($client);
        $this->syncLocations($client);

        return self::SUCCESS;
    }

    private function syncLanguages(DataForSeoClient $client): void
    {
        $this->info('Sincronizando idiomas (serp/google/languages)...');

        $rows = $this->firstTaskResult($client->get('serp/google/languages'));

        Language::query()->upsert(
            $rows->map(fn (array $row) => [
                'language_code' => $row['language_code'],
                'language_name' => $row['language_name'],
            ])->all(),
            ['language_code'],
            ['language_name'],
        );

        $this->info("Idiomas sincronizados: {$rows->count()}.");
    }

    /**
     * serp/google/languages y keywords_data/google_ads/languages son
     * catálogos distintos en DataForSEO: un language_code válido para
     * rastrear posiciones (serp/google/organic/task_post) puede ser
     * rechazado por keywords_data/google_ads/* con "Invalid Field:
     * 'language_code'." (ej. "es-419", que sí sale en el primer
     * catálogo). Se marca aparte para que el selector de idioma del
     * proyecto pueda restringirse solo a los códigos que no rompen el
     * enriquecimiento de volumen ni las ideas de keywords.
     */
    private function syncGoogleAdsKeywordLanguages(DataForSeoClient $client): void
    {
        $this->info('Sincronizando idiomas de keywords_data/google_ads/languages...');

        $rows = $this->firstTaskResult($client->get('keywords_data/google_ads/languages'));

        Language::query()->update(['valid_for_google_ads_keywords' => false]);

        Language::query()
            ->whereIn('language_code', $rows->pluck('language_code'))
            ->update(['valid_for_google_ads_keywords' => true]);

        $this->info("Idiomas válidos para keywords_data/google_ads: {$rows->count()}.");
    }

    private function syncLocations(DataForSeoClient $client): void
    {
        $endpoint = 'serp/google/locations';

        if ($country = $this->option('country')) {
            $endpoint .= '/'.$country;
        }

        $this->info("Sincronizando ubicaciones ({$endpoint})...");

        $rows = $this->firstTaskResult($client->get($endpoint));

        $toRow = fn (array $row) => [
            'location_code' => $row['location_code'],
            'location_name' => $row['location_name'],
            'location_name_canonical' => $row['location_name'],
            'country_iso_code' => $row['country_iso_code'],
            'location_type' => $row['location_type'],
            'parent_code' => $row['location_code_parent'] ?? null,
        ];

        // DataForSEO incluye ubicaciones cuyo location_code_parent
        // apunta a un location_code que no aparece en ninguna otra fila
        // de esta misma respuesta (referencia huérfana del lado de
        // DataForSEO, no un bug de paginación nuestro). Solo los
        // location_code presentes en $rows quedan insertados tras la
        // primera pasada, así que cualquier parent_code fuera de ese
        // conjunto violaría la FK — se descarta a null en vez de
        // tronar el comando completo.
        $knownCodes = $rows->pluck('location_code')->flip();

        // Primera pasada sin parent_code: si un hijo llegara antes que
        // su padre dentro del mismo chunk, insertarlo ya con
        // parent_code violaría la FK autoreferenciada.
        $rows->chunk(self::CHUNK_SIZE)->each(
            fn (Collection $chunk) => Location::query()->upsert(
                $chunk->map(fn (array $row) => [...$toRow($row), 'parent_code' => null])->all(),
                ['location_code'],
                ['location_name', 'location_name_canonical', 'country_iso_code', 'location_type'],
            )
        );

        // Segunda pasada: ahora que todos los location_code ya existen,
        // setear parent_code es seguro sin importar el orden del lote,
        // salvo las referencias huérfanas descartadas arriba. SQLite
        // exige que el VALUES de un upsert satisfaga las columnas NOT
        // NULL aunque el conflicto las descarte, así que se manda la
        // fila completa otra vez y solo se actualiza parent_code.
        $orphanedParents = 0;
        $rows->chunk(self::CHUNK_SIZE)->each(
            function (Collection $chunk) use ($toRow, $knownCodes, &$orphanedParents) {
                $mapped = $chunk->map(function (array $row) use ($toRow, $knownCodes, &$orphanedParents) {
                    $row = $toRow($row);

                    if ($row['parent_code'] !== null && ! $knownCodes->has($row['parent_code'])) {
                        $orphanedParents++;
                        $row['parent_code'] = null;
                    }

                    return $row;
                })->all();

                Location::query()->upsert($mapped, ['location_code'], ['parent_code']);
            }
        );

        if ($orphanedParents > 0) {
            $this->warn("{$orphanedParents} ubicaciones con parent_code huérfano (no existe en el catálogo devuelto por DataForSEO) se guardaron sin padre.");
        }

        $this->info("Ubicaciones sincronizadas: {$rows->count()}.");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function firstTaskResult(DataForSeoResponseData $response): Collection
    {
        foreach ($response->tasks as $task) {
            return collect($task->result ?? []);
        }

        return collect();
    }
}
