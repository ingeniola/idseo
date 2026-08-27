<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2, "Integración con Google Search Console" (sección 5 del
     * SPEC). Datos de searchAnalytics.query con dimensiones
     * [date, query] — la combinación más útil para una agencia de SEO
     * (qué búsquedas de verdad traen clics/impresiones), no todas las
     * dimensiones que soporta la API (page/device/country quedan fuera
     * de este alcance inicial).
     *
     * Único por (project_id, date, query): SyncSearchConsoleData
     * vuelve a pedir una ventana de días (los datos de Search Console
     * se revisan/finalizan después de aparecer por primera vez) y hace
     * upsert, nunca inserta filas nuevas para el mismo día+query.
     *
     * ctr/position son los que Google devuelve tal cual (no
     * normalizados): ctr es una fracción 0-1, position es un promedio
     * con decimales, no una posición entera como en `rankings`.
     */
    public function up(): void
    {
        Schema::create('search_console_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('query');
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('impressions');
            $table->decimal('ctr', 6, 4);
            $table->decimal('position', 6, 2);
            $table->timestamps();

            $table->unique(['project_id', 'date', 'query']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_metrics');
    }
};
