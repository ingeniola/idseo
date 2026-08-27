<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3, "Auditoría técnica on-page" (sección 4 del SPEC).
     * `issue_type` guarda siempre un valor del enum
     * App\DataForSeo\OnPage\Enums\AuditIssueType (sección 4: "el
     * catálogo de tipos de issue debe vivir en un enum PHP... no en
     * strings sueltos") — ProcessOnPageAuditPostback solo genera una
     * fila por cada `check` de on_page/pages que esté en ese enum,
     * nunca por un nombre de check no reconocido (ver el docblock del
     * enum: no se puede saber si un check desconocido es "true = mal"
     * o "true = normal" sin haberlo verificado contra la
     * documentación).
     *
     * `url` va hasta 2048 (mismo límite que backlinks.source_url /
     * rankings.url: una URL real puede pasar los 255 por defecto).
     */
    public function up(): void
    {
        Schema::create('audit_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('site_audits')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('issue_type');
            $table->string('severity');
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['audit_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_issues');
    }
};
