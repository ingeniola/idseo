<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sin timestamps() de Laravel: es un snapshot inmutable,
     * `captured_at` ya cumple ese rol. Guardar el SERP completo permite
     * análisis retrospectivo de competidores sin volver a pagar la
     * llamada (sección 4 del SPEC).
     */
    public function up(): void
    {
        Schema::create('serp_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->restrictOnDelete();
            $table->timestamp('captured_at');
            $table->json('raw_response');
            $table->json('top_results');

            $table->index(['keyword_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_snapshots');
    }
};
