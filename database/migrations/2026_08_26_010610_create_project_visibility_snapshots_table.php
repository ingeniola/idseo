<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Métricas de visibilidad agregada por proyecto, calculadas
     * localmente sin costo de API (sección 5.3 y job
     * CalculateProjectVisibility de la sección 6 del SPEC). No estaba
     * en el listado de tablas de la sección 4; se agrega ahora porque
     * es lo que alimenta "la gráfica de... visibilidad agregada del
     * proyecto" — la gráfica en sí es la Fase 1, paso 9.
     */
    public function up(): void
    {
        Schema::create('project_visibility_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->date('calculated_at');
            $table->decimal('visibility_score', 5, 2);
            $table->unsignedInteger('tracked_keywords_count');
            $table->unsignedInteger('keywords_in_top_3');
            $table->unsignedInteger('keywords_in_top_10');
            $table->unsignedInteger('keywords_in_top_20');
            $table->decimal('average_position', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_visibility_snapshots');
    }
};
