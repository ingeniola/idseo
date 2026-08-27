<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3, "Backlinks: perfil, dominios referentes, enlaces
     * perdidos, comparativa contra competidores" (sección 5 del SPEC).
     * Esquema base de la sección 4, con dos cambios deliberados:
     *
     * - `domain` (no está en la sección 4): sin esto no se puede
     *   guardar el perfil de un dominio competidor para comparar
     *   ("comparativa contra competidores" es parte explícita del
     *   alcance) — solo se podría guardar el del propio proyecto.
     *   Cuando domain = projects.domain, es el perfil propio; cuando
     *   es otro dominio, es una instantánea de comparación.
     * - `captured_at` con unique (project_id, domain, captured_at):
     *   un snapshot por día por dominio, igual que
     *   project_visibility_snapshots y serp_competitors — permite ver
     *   evolución en el tiempo, no solo el estado actual.
     *
     * `rank` de la API se guarda en la columna `domain_rank` (nombre
     * de la sección 4): DataForSEO llama a la métrica "rank" a secas
     * en la respuesta, verificado contra la documentación pública
     * disponible.
     */
    public function up(): void
    {
        Schema::create('backlink_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('domain');
            $table->date('captured_at');
            $table->unsignedBigInteger('total_backlinks');
            $table->unsignedInteger('referring_domains');
            $table->unsignedInteger('referring_ips');
            $table->unsignedSmallInteger('domain_rank')->nullable();
            $table->unsignedBigInteger('broken_backlinks');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'domain', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backlink_summaries');
    }
};
