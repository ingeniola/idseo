<?php

declare(strict_types=1);

namespace App\DataForSeo\AiOptimization;

use App\DataForSeo\DataForSeoClient;
use App\Models\LlmMention;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Fase 3, "Monitoreo de menciones en LLMs (GEO)" (sección 5 del SPEC).
 * ai_optimization/llm_mentions/search/live: busca cómo se menciona el
 * dominio del proyecto en respuestas de IA (ChatGPT o AI Overview de
 * Google). Es Live-only — no tiene modo Standard/task_post (confirmado
 * por búsqueda cruzada, docs.dataforseo.com bloqueado en este
 * entorno) — así que, igual que Backlinks, es una consulta interactiva
 * a demanda: la sección 3.2 del SPEC prohíbe disparar Live de forma
 * automática o en bucle, así que no hay job programado para esto.
 *
 * Cada llamada guarda una instantánea nueva (sin upsert): volver a
 * buscar el mismo target es una captura nueva en el tiempo, igual que
 * backlink_summaries/serp_snapshots.
 */
class SearchLlmMentions
{
    private const ENDPOINT = 'ai_optimization/llm_mentions/search/live';

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    /**
     * @return Collection<int, LlmMention>
     */
    public function execute(Project $project, string $platform): Collection
    {
        $response = $this->client->post(self::ENDPOINT, [[
            'target' => [['domain' => $project->domain]],
            'platform' => $platform,
            'language_code' => $project->default_language_code,
            'location_code' => $project->default_location_code,
        ]], $project->id);

        $items = $response->tasks[0]->result[0]['items'] ?? [];
        $capturedAt = now();

        /** @var Collection<int, LlmMention> $mentions */
        $mentions = new Collection;

        foreach ((is_array($items) ? $items : []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mentions->push(LlmMention::query()->create([
                'project_id' => $project->id,
                'platform' => $platform,
                'question' => $item['question'] ?? null,
                'answer' => $item['answer'] ?? null,
                'sources' => $item['sources'] ?? null,
                'captured_at' => $capturedAt,
                'raw' => $item,
            ]));
        }

        return $mentions;
    }
}
