<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2FA para usuarios internos (sección 9 y Fase 1 paso 12 del SPEC),
     * vía el proveedor TOTP nativo de Filament
     * (Filament\Auth\MultiFactor\App\AppAuthentication). Ambas
     * columnas se guardan encriptadas (ver casts en el modelo User):
     * el secreto TOTP y los códigos de recuperación son tan sensibles
     * como una contraseña.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
