<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3, "Monitoreo de menciones en LLMs (GEO)" (sección 5 del
     * SPEC). Sin esquema propuesto en la sección 4 — diseñado desde
     * cero. ai_optimization/llm_mentions/search/live es Live-only (no
     * tiene modo Standard/task_post — confirmado por búsqueda cruzada,
     * docs.dataforseo.com bloqueado en este entorno), así que cada
     * fila es una instantánea de una búsqueda disparada a demanda,
     * igual que backlink_summaries/serp_snapshots — no hay unique
     * constraint porque volver a buscar el mismo target es una
     * instantánea nueva en el tiempo, no un upsert.
     *
     * Campos verificados: `question`/`answer`/`sources` (lista de
     * enlaces citados por el LLM) por búsqueda cruzada.
     */
    public function up(): void
    {
        Schema::create('llm_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('platform');
            $table->text('question')->nullable();
            $table->longText('answer')->nullable();
            $table->json('sources')->nullable();
            $table->timestamp('captured_at');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_mentions');
    }
};
