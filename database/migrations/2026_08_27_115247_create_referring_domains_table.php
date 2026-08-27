<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3, "Backlinks: ... dominios referentes ..." (sección 4 y 5
     * del SPEC). A diferencia de `backlinks.is_lost` (que sí viene
     * directo de la API, campo `is_lost` confirmado en
     * backlinks/backlinks/live), no se encontró en la documentación
     * pública disponible un campo equivalente confirmado para
     * backlinks/referring_domains/live — así que `is_lost` aquí se
     * calcula localmente: un dominio que aparecía en una
     * actualización anterior y ya no aparece en la más reciente se
     * marca perdido, mismo criterio que usa CalculateSerpCompetitors
     * (Fase 2) para podar dominios que ya no aparecen "hoy".
     */
    public function up(): void
    {
        Schema::create('referring_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('domain');
            $table->unsignedInteger('backlinks_count');
            $table->date('first_seen');
            $table->unsignedSmallInteger('domain_rank')->nullable();
            $table->boolean('is_lost')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referring_domains');
    }
};
