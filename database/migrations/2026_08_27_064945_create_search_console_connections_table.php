<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2, "Integración con Google Search Console (OAuth propio,
     * datos gratis)" — sección 5 del SPEC. Una conexión por proyecto
     * (unique en project_id): un proyecto rastrea un solo dominio, así
     * que solo tiene sentido una propiedad de Search Console conectada
     * a la vez.
     *
     * access_token/refresh_token se guardan encriptados (ver casts en
     * el modelo), igual criterio que el secreto TOTP de 2FA (sección 9
     * del SPEC): son credenciales, no datos de negocio. connected_by
     * es nullable + nullOnDelete (igual que audit_logs.user_id y
     * keyword_research_sessions.user_id): la conexión debe sobrevivir
     * aunque se borre el usuario que la creó.
     */
    public function up(): void
    {
        Schema::create('search_console_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->restrictOnDelete();
            $table->string('site_url');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_connections');
    }
};
