<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2, "Alertas" (sección 5 y job DetectRankingAlerts de la
     * sección 6 del SPEC). No estaba en el listado de tablas de la
     * sección 4; se agrega ahora, igual que project_visibility_snapshots
     * y serp_competitors en su momento.
     *
     * project_id vive aquí además de a través de keyword_id, aunque es
     * derivable, porque agrupar y filtrar por proyecto (para el resumen
     * diario por correo y para el filtro en el panel) sin un join es lo
     * que realmente se usa; keyword_id solo alcanzaría con un join
     * extra en cada consulta.
     *
     * Fila inmutable una vez creada (igual que audit_logs): solo
     * created_at, sin updated_at. notified_at se llena aparte, cuando
     * SendRankingAlertDigest efectivamente manda el correo — no es una
     * "edición" del hecho, es un dato sobre su entrega.
     *
     * Único por (keyword_id, type, triggered_at): si DetectRankingAlerts
     * corre dos veces el mismo día sin que haya una Ranking nueva de por
     * medio, no debe duplicar la alerta ni volver a notificarla.
     */
    public function up(): void
    {
        Schema::create('ranking_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('keyword_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->unsignedSmallInteger('current_position')->nullable();
            $table->date('triggered_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['keyword_id', 'type', 'triggered_at']);
            $table->index(['project_id', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_alerts');
    }
};
