<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2, "Investigación de keywords (DataForSEO Labs)" (sección 5
     * del SPEC). Un registro por cada búsqueda de ideas a partir de una
     * keyword semilla — sección 4 del SPEC. Es un registro inmutable de
     * lo que se pidió y cuánto costó (igual que audit_logs): solo
     * created_at, sin updated_at.
     *
     * user_id es nullable + nullOnDelete (igual que audit_logs.user_id):
     * la sesión debe sobrevivir aunque se borre el usuario que la
     * disparó.
     */
    public function up(): void
    {
        Schema::create('keyword_research_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('seed_keyword');
            $table->string('source_endpoint');
            $table->decimal('cost', 10, 6);
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_research_sessions');
    }
};
