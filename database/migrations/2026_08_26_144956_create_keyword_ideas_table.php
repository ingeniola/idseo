<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2, "Investigación de keywords" (sección 4 y 5 del SPEC).
     * A diferencia de keyword_research_sessions, esta tabla sí es
     * mutable: is_selected cambia cuando alguien promueve una idea a
     * `keywords` para seguimiento (sección 4: "is_selected permite
     * promover ideas a keywords"). Por eso lleva timestamps() completos
     * aunque el SPEC no los liste explícitamente para esta tabla.
     *
     * cpc/competition usan la misma precisión decimal que
     * keywords.cpc/keywords.competition, porque una idea seleccionada
     * termina copiándose a esas columnas al promoverla.
     *
     * intent se guarda como string plano, no casteado al enum
     * SearchIntent: la presencia de search_intent_info en la respuesta
     * de keyword_ideas/live no está confirmada al 100% (ver el
     * docblock del enum) y un cast de Eloquent fallaría duro ante un
     * valor no anticipado.
     */
    public function up(): void
    {
        Schema::create('keyword_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('keyword_research_sessions')->restrictOnDelete();
            $table->string('keyword');
            $table->unsignedInteger('search_volume')->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->decimal('competition', 3, 2)->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->string('intent')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_ideas');
    }
};
