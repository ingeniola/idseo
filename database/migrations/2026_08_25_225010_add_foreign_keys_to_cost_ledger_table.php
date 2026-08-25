<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `clients` y `projects` ya existen en este punto (Fase 1, paso 4):
     * cierra la constraint que la migración de cost_ledger del paso 3
     * dejó pendiente a propósito.
     */
    public function up(): void
    {
        Schema::table('cost_ledger', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->restrictOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cost_ledger', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['project_id']);
        });
    }
};
