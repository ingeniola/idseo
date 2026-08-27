<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3, "Monitoreo de reseñas y Google Business Profile" (sección
     * 5 del SPEC). No hay esquema propuesto en la sección 4 para este
     * módulo — diseñado desde cero siguiendo la misma convención que
     * las demás tablas de Fase 3 (backlinks, site_audits).
     *
     * Campos verificados contra business_data/google/reviews/task_get
     * por búsqueda cruzada (docs.dataforseo.com bloqueado en este
     * entorno): review_id, profile_name, profile_image_url,
     * rating.value, review_text, timestamp, owner_answer,
     * owner_timestamp, local_guide — nombres reales de la API, no
     * inventados.
     *
     * `review_id` es el identificador único de DataForSEO para la
     * reseña; unique (project_id, review_id) evita duplicar filas al
     * re-sincronizar.
     */
    public function up(): void
    {
        Schema::create('business_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('review_id');
            $table->string('reviewer_name')->nullable();
            $table->string('profile_image_url')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('review_text')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('owner_answer')->nullable();
            $table->timestamp('owner_answered_at')->nullable();
            $table->boolean('is_local_guide')->default(false);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'review_id']);
            $table->index(['project_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_reviews');
    }
};
