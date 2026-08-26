<?php

declare(strict_types=1);

namespace App\RankTracking;

/**
 * Interpreta el arreglo `items` de un resultado SERP "advanced" de
 * DataForSEO (serp/google/organic). Cada elemento tiene un `type`
 * (organic, featured_snippet, local_pack, people_also_ask, images,
 * video, ...) y una posición (rank_group = posición dentro del mismo
 * tipo, rank_absolute = posición entre todos los elementos de la
 * página) — nombres verificados contra la documentación pública antes
 * de implementar esto (sección 3.1 del SPEC).
 *
 * El tipo exacto para AI Overview no está confirmado en la
 * documentación pública disponible; se detecta por coincidencia
 * parcial ("contains ai_overview") como mejor esfuerzo, a verificar
 * contra una respuesta real.
 */
class SerpResultParser
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $trackedDomain,
    ) {}

    /**
     * Posición orgánica (rank_group) y URL del primer item type=organic
     * cuyo domain coincide con el dominio del proyecto. Null si el
     * dominio no aparece entre los resultados devueltos.
     *
     * @return array{position: int, url: string|null}|null
     */
    public function findOrganicRanking(): ?array
    {
        foreach ($this->items as $item) {
            if (($item['type'] ?? null) !== 'organic') {
                continue;
            }

            $domain = (string) ($item['domain'] ?? '');

            if ($domain === '' || ! $this->matchesTrackedDomain($domain)) {
                continue;
            }

            $rankGroup = $item['rank_group'] ?? null;

            if ($rankGroup === null) {
                continue;
            }

            return [
                'position' => (int) $rankGroup,
                'url' => $item['url'] ?? null,
            ];
        }

        return null;
    }

    /**
     * @return array<int, string> tipos de SERP feature presentes (sin duplicados)
     */
    public function detectedFeatures(): array
    {
        $types = [];

        foreach ($this->items as $item) {
            $type = $item['type'] ?? null;

            if (is_string($type) && $type !== 'organic' && $type !== 'paid') {
                $types[$type] = true;
            }
        }

        return array_keys($types);
    }

    public function hasFeaturedSnippet(): bool
    {
        return in_array('featured_snippet', $this->detectedFeatures(), true);
    }

    public function hasLocalPack(): bool
    {
        return in_array('local_pack', $this->detectedFeatures(), true);
    }

    private function matchesTrackedDomain(string $domain): bool
    {
        $domain = strtolower(ltrim($domain, '.'));
        $tracked = strtolower(ltrim($this->trackedDomain, '.'));

        return $domain === $tracked || str_ends_with($domain, '.'.$tracked);
    }
}
