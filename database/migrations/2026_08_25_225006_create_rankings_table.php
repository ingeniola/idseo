<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de mayor crecimiento del sistema (sección 4 del SPEC: ~150k
     * filas/mes con 100 clientes). El índice (keyword_id, checked_at) es
     * el mínimo indispensable desde el día uno; particionamiento por
     * rango de fecha o política de archivado es una decisión de
     * operación/DBA para cuando haya volumen real, no algo que esta
     * migración de MySQL deba forzar hoy.
     */
    public function up(): void
    {
        Schema::create('rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->restrictOnDelete();
            $table->date('checked_at');
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->string('url', 2048)->nullable();
            $table->json('serp_features')->nullable();
            $table->unsignedInteger('estimated_traffic')->nullable();
            $table->boolean('is_featured_snippet')->default(false);
            $table->boolean('is_local_pack')->default(false);
            $table->timestamps();

            $table->index(['keyword_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rankings');
    }
};
