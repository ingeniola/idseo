<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3, "Backlinks" (sección 4 y 5 del SPEC). is_lost se lee
     * directo del campo `is_lost` de backlinks/backlinks/live
     * (confirmado contra la documentación pública) — a diferencia de
     * referring_domains, donde esa confirmación no se encontró (ver
     * la migración de esa tabla).
     *
     * source_url/target_url van hasta 2048 caracteres (mismo límite
     * que rankings.url): una URL real de verdad puede pasar los 255
     * por defecto (query strings, rutas profundas). Por eso la
     * identidad de la fila para el upsert no es (source_url,
     * target_url) directo — un índice único sobre dos columnas de
     * 2048 caracteres supera el límite de longitud de clave de MySQL
     * (3072 bytes con utf8mb4) — sino `link_hash`, un sha1 de ambas
     * URLs, de longitud fija.
     */
    public function up(): void
    {
        Schema::create('backlinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('source_url', 2048);
            $table->string('source_domain');
            $table->string('target_url', 2048);
            $table->string('anchor', 2048)->nullable();
            $table->boolean('dofollow');
            $table->date('first_seen');
            $table->date('last_seen');
            $table->boolean('is_lost')->default(false);
            $table->unsignedSmallInteger('domain_rank')->nullable();
            $table->char('link_hash', 40);
            $table->timestamps();

            $table->unique(['project_id', 'link_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backlinks');
    }
};
