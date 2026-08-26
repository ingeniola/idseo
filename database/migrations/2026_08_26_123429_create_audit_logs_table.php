<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoría de autorización (sección 9 y Fase 1 paso 12 del SPEC):
     * quién entró, a quién se le negó acceso, y quién disparó una
     * acción sensible (descarga de reporte, llamada paga manual) —
     * no un log de auditoría genérico de toda la aplicación.
     *
     * user_id es nullable a propósito: un intento de login fallido no
     * tiene un usuario resuelto (podría ser un correo que ni existe),
     * pero igual es un evento que vale la pena poder consultar por
     * correo/IP. Sin updated_at: una fila de auditoría no se edita
     * nunca después de escrita.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['event', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
