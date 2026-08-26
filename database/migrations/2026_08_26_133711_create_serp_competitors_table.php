<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2, "Análisis de competidores derivado de los SERP snapshots"
     * (sección 5 del SPEC). Costo cero: se calcula localmente a partir
     * de `serp_snapshots.top_results`, sin llamar a DataForSEO. Un
     * dominio puede aparecer varios días seguidos con estadísticas
     * distintas, así que la identidad de una fila es
     * (project_id, domain, calculated_at) — igual que
     * project_visibility_snapshots, un snapshot por día, no historial
     * de cambios intradiario.
     */
    public function up(): void
    {
        Schema::create('serp_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('domain');
            $table->unsignedInteger('keywords_overlap');
            $table->decimal('avg_position', 6, 2);
            $table->decimal('visibility_score', 5, 2);
            $table->date('calculated_at');
            $table->timestamps();

            $table->unique(['project_id', 'domain', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_competitors');
    }
};
