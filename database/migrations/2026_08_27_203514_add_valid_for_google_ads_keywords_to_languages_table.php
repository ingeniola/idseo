<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `serp/google/languages` (usado hasta ahora para poblar esta
     * tabla) y `keywords_data/google_ads/languages` son catálogos
     * distintos en DataForSEO: un language_code válido para rastrear
     * posiciones (serp/google/organic/task_post) puede ser rechazado
     * por keywords_data/google_ads/* (ej. "es-419", "Invalid Field:
     * 'language_code'.") — pasó en producción con dos proyectos
     * reales antes de esta columna. Sin distinguir ambos catálogos,
     * el selector de idioma del proyecto no tiene forma de evitar que
     * alguien elija un código que rompe el enriquecimiento de volumen
     * o las ideas de keywords.
     */
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->boolean('valid_for_google_ads_keywords')->default(false)->after('language_name');
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('valid_for_google_ads_keywords');
        });
    }
};
