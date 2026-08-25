<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * location_code y language_code no llevan foreign key hacia
     * locations/languages a propósito: son solo un hint de catálogo, y
     * exigir la integridad referencial complicaría los seeds/factories
     * sin aportar nada que la capa de aplicación no pueda validar mejor
     * (ej. antes de prometerle SEO local a un cliente, sección 4 del SPEC).
     */
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('keyword');
            $table->unsignedInteger('location_code');
            $table->string('language_code');
            $table->json('tags')->nullable();
            $table->unsignedInteger('search_volume')->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->decimal('competition', 3, 2)->nullable();
            $table->timestamp('volume_updated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'keyword', 'location_code', 'language_code'], 'keywords_project_keyword_location_language_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
