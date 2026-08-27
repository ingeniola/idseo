<?php

declare(strict_types=1);

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3, "Auditoría técnica on-page" (sección 4 y 5 del SPEC).
     * Esquema exacto de la sección 4. `task_id` es el id de tarea de
     * DataForSEO (denormalizado aquí para no tener que pasar por la
     * relación polimórfica en `dataforseo_tasks` solo para mostrarlo
     * en pantalla); la fila real en `dataforseo_tasks` sigue siendo la
     * fuente de verdad de payload_sent/payload_received/reintentos.
     *
     * `status` reutiliza el mismo enum que dataforseo_tasks
     * (pending/completed/failed): es el mismo concepto, sin matices
     * adicionales — on_page/task_post es modo Standard (sección 3.2),
     * "no aplica" cache (sección 3.6: "Auditoría on-page: no aplica,
     * bajo demanda") y se dispara a demanda desde la pestaña del
     * proyecto, nunca en un job programado.
     */
    public function up(): void
    {
        Schema::create('site_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('task_id')->nullable();
            $table->string('status')->default(DataForSeoTaskStatus::Pending->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('pages_crawled')->nullable();
            $table->decimal('onpage_score', 5, 2)->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->timestamps();

            $table->index('task_id');
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_audits');
    }
};
