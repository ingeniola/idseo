<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GenerateScheduledReports (Fase 1, paso 10 del SPEC) usa
     * firstOrCreate sobre estas cuatro columnas para no duplicar el
     * reporte mensual de un proyecto si el job programado se
     * corriera dos veces — el mismo patrón de idempotencia por llave
     * natural usado en `rankings` y `project_visibility_snapshots`.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->unique(
                ['project_id', 'template_id', 'period_start', 'period_end'],
                'reports_project_template_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique('reports_project_template_period_unique');
        });
    }
};
